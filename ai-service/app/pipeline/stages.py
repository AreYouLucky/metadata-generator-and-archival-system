"""The work of each stage. Pure functions over the pipeline context, so they can be
tested without Celery or Laravel; app.pipeline.tasks wires them into the chain."""

import json
import logging
import subprocess  # noqa: S404 - only for its exception types
from collections.abc import Callable
from pathlib import Path
from typing import Any

from app.config import get_settings
from app.pipeline import audio, checksum, frames, objects, ocr, probe, proxy, scenes
from app.providers.diarization import get_diarization_provider
from app.providers.transcription import get_transcription_provider
from app.providers.transcription.assemblyai import AssemblyAIProvider
from app.schemas.pipeline import PipelineContext, StageName
from app.schemas.visual import FrameInfo
from app.services.metadata import MetadataGenerator
from app.services.vision import FrameDescriber
from app.storage import relative, resolve, work_dir

Progress = Callable[[int], None]

logger = logging.getLogger(__name__)


class SkipStage(Exception):  # noqa: N818 - a signal, not an error
    """Raised when a stage has nothing to do; it is reported as `skipped`, not `failed`."""


def run_checksum(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    result = checksum.compute(resolve(context.request.video_path), on_progress)
    return dict(result)


def run_probe(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    path = resolve(context.request.video_path)
    raw_ffprobe = probe.run_ffprobe(path)
    on_progress(50)
    raw_mediainfo = probe.run_mediainfo(path)
    summary = probe.summarise(raw_ffprobe, raw_mediainfo)

    duration = summary.get("duration")
    limit = context.request.options.max_duration_seconds
    if limit and duration and duration > limit:
        raise probe.ProbeError(
            f"The video is {duration / 60:.0f} minutes long; the limit is {limit / 60:.0f} minutes."
        )
    context.duration = duration
    context.container = str(raw_ffprobe.get("format", {}).get("format_name") or "") or None
    context.playable = proxy.is_browser_playable(context.container, summary)
    context.interlaced = str(summary.get("scan_type") or "").startswith("interlaced")
    return {**summary, "raw_probe": {"ffprobe": raw_ffprobe, "mediainfo": raw_mediainfo}}


def run_extract_audio(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    target = work_dir(context.request.record_id) / "audio.mp3"
    audio.extract(resolve(context.request.video_path), target, context.duration, on_progress)
    context.audio_path = relative(target)
    return {"audio_path": context.audio_path, "waveform_path": write_waveform(context, target)}


def write_waveform(context: PipelineContext, audio_file: Path) -> str | None:
    """`records/{id}/waveform.json` for the workspace timeline. Outside the temporary work
    folder so cleanup keeps it; a failure here never fails the stage."""
    try:
        peaks = audio.waveform_peaks(audio_file)
    except (audio.AudioError, OSError, subprocess.SubprocessError):
        logger.warning("record_id=%s waveform failed", context.request.record_id, exc_info=True)
        return None
    target = resolve(f"records/{context.request.record_id}/waveform.json")
    target.write_text(
        json.dumps(
            {
                "version": 1,
                "peaks_per_second": audio.WAVEFORM_PEAKS_PER_SECOND,
                "peaks": peaks,
            },
            separators=(",", ":"),
        )
    )
    return relative(target)


def run_transcribe(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    if context.audio_path is None:
        raise SkipStage("No audio was extracted.")
    provider = get_transcription_provider()
    audio_file = resolve(context.audio_path)
    if isinstance(provider, AssemblyAIProvider):
        # One request gives both the lines and, when asked for, the speakers.
        result = provider.transcribe(
            audio_file,
            context.duration,
            on_progress,
            speaker_labels=context.request.options.diarization,
        )
    else:
        result = provider.transcribe(audio_file, context.duration, on_progress)
    context.segments = result.segments
    context.language = result.language
    context.speaker_turns = result.speaker_turns
    return result.model_dump()


def run_diarize(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    """Who speaks when. Laravel gives each transcript line the speaker who talks longest
    during it. Reuses the transcription's labels when the provider gave them."""
    if not context.segments:
        raise SkipStage("Nothing was said in the video, so there are no speakers to find.")
    if context.speaker_turns:
        return {
            "provider": "assemblyai",
            "model": None,
            "turns": [turn.model_dump() for turn in context.speaker_turns],
            "usage": [],
        }
    if context.audio_path is None:
        raise SkipStage("No audio was extracted.")
    result = get_diarization_provider().diarize(
        resolve(context.audio_path), context.duration, on_progress
    )
    if not result.turns:
        raise SkipStage("No speech was found to tell speakers apart.")
    return result.model_dump()


def run_proxy(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    """An access copy only when browsers can't play the master (ProRes, MXF, 10-bit...)."""
    if context.playable:
        raise SkipStage("The master plays in browsers, so no access copy is needed.")
    record_id = context.request.record_id
    target = resolve(f"records/{record_id}/proxy/access.mp4")
    proxy.transcode(
        resolve(context.request.video_path),
        target,
        context.duration,
        get_settings().proxy_max_height,
        context.interlaced,
        on_progress,
    )
    on_progress(92)
    sums = checksum.compute(target)
    on_progress(96)
    summary = probe.summarise(probe.run_ffprobe(target), probe.run_mediainfo(target))
    return {
        "path": relative(target),
        "md5": sums["md5"],
        "sha256": sums["sha256"],
        "file_size": sums["file_size"],
        "probe": summary,
    }


def run_scenes(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    settings = get_settings()
    shots = scenes.detect(
        resolve(context.request.video_path), context.duration, settings.scene_threshold, on_progress
    )
    context.shots = scenes.cap(shots, settings.max_shots)
    return {"shots": [shot.model_dump() for shot in context.shots]}


def run_frames(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    """Key frames in `records/{id}/frames/` (replaced on a re-run), which frames repeat
    the one before, and a suggested default thumbnail."""
    settings = get_settings()
    planned = frames.plan(
        context.duration, context.shots, settings.frame_interval_sec, settings.max_frames
    )
    taken = frames.extract_all(
        resolve(context.request.video_path),
        resolve(f"records/{context.request.record_id}/frames"),
        planned,
        settings.frame_max_height,
        on_progress,
    )
    looks = [frames.look(path) for _, path in taken]
    on_progress(90)
    repeats = frames.duplicates(looks)
    context.frames = [
        FrameInfo(
            time_sec=frame.time_sec,
            path=relative(path),
            is_scene_start=frame.is_scene_start,
            duplicate_of=repeats[index],
        )
        for index, (frame, path) in enumerate(taken)
    ]
    return {
        "frames": [
            {"time_sec": frame.time_sec, "path": frame.path, "is_scene_start": frame.is_scene_start}
            for frame in context.frames
        ],
        "thumbnail_index": frames.suggested_thumbnail(
            looks, [frame.time_sec for frame in context.frames], repeats
        ),
    }


def _distinct(context: PipelineContext) -> dict[int, Path]:
    """Frames that don't repeat an earlier one, by index: the only ones analysed."""
    return {
        index: resolve(frame.path)
        for index, frame in enumerate(context.frames)
        if frame.duplicate_of is None
    }


def _source(context: PipelineContext, index: int) -> int:
    """The frame whose results this frame shares (itself unless it is a repeat)."""
    repeat_of = context.frames[index].duplicate_of
    return index if repeat_of is None else repeat_of


def run_vision(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    if not context.frames:
        raise SkipStage("No frames were taken, so there is nothing to describe.")
    on_progress(5)
    descriptions, usage = FrameDescriber().describe(_distinct(context))
    for index, frame in enumerate(context.frames):
        frame.description = descriptions.get(_source(context, index))
    return {
        "model": get_settings().vision_model,
        "frames": [
            {"time_sec": frame.time_sec, "description": frame.description}
            for frame in context.frames
        ],
        "usage": [item.model_dump() for item in usage],
    }


def run_ocr(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    if not context.frames:
        raise SkipStage("No frames were taken, so there is no text to read.")
    distinct = _distinct(context)
    texts: dict[int, str] = {}
    for done, (index, path) in enumerate(distinct.items(), start=1):
        texts[index] = ocr.read(path)
        on_progress(int(done * 95 / len(distinct)))
    for index, frame in enumerate(context.frames):
        frame.ocr_text = texts.get(_source(context, index)) or None
    return {
        "frames": [{"time_sec": frame.time_sec, "text": frame.ocr_text} for frame in context.frames]
    }


def run_objects(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    if not context.frames:
        raise SkipStage("No frames were taken, so there is nothing to detect.")
    distinct = _distinct(context)
    on_progress(5)
    found = dict(zip(distinct, objects.detect(list(distinct.values())), strict=True))
    results = []
    for index, frame in enumerate(context.frames):
        labels, faces = found[_source(context, index)]
        results.append(
            {
                "time_sec": frame.time_sec,
                "objects": [item.model_dump() for item in labels],
                "face_count": faces,
            }
        )
    return {"frames": results}


def run_generate_metadata(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    """From the transcript, the frames' on-screen content, or both (a slideshow with
    music has no transcript but can still be described)."""
    options = context.request.options
    visual = context.visual_frames() if options.visual_analysis else []
    if not context.segments and not visual:
        if "transcribe" in context.failed or "transcribe" in context.skipped:
            raise SkipStage("Skipped because the transcribe step did not finish.")
        raise SkipStage("Nothing was said or shown in the video, so there is nothing to describe.")
    on_progress(10)
    result = MetadataGenerator().generate(
        context.segments,
        options.preset,
        language=context.language,
        custom_prompt=options.custom_prompt,
        title_count=options.title_count,
        duration=context.duration,
        visual=visual,
    )
    return {
        "preset": options.preset.name,
        "custom_prompt": options.custom_prompt,
        **result.model_dump(),
    }


def restore(stage: StageName, context: PipelineContext) -> None:
    """For a partial run (a retry of one stage): give the context what `stage` produced
    the first time, without reporting anything, so the stages that run again have their
    inputs. Cheap facts are measured again (probe); stored results come from the seed
    Laravel sent; the extracted audio is reused when it is still in the work folder."""
    request = context.request
    seed = request.seed
    needs_audio = request.runs("transcribe") or request.runs("diarize")

    if stage == "probe":
        run_probe(context, _quiet)
    elif stage == "extract_audio" and needs_audio:
        audio_file = work_dir(request.record_id) / "audio.mp3"
        if audio_file.is_file():
            context.audio_path = relative(audio_file)
        else:
            audio.extract(resolve(request.video_path), audio_file, context.duration, _quiet)
            context.audio_path = relative(audio_file)
    elif stage == "transcribe" and seed is not None and seed.segments is not None:
        context.segments = seed.segments
        context.language = seed.language
    elif stage == "scenes" and seed is not None and seed.shots is not None:
        context.shots = seed.shots
    elif stage == "frames" and seed is not None and seed.frames is not None:
        context.frames = [frame.model_copy(update={"duplicate_of": None}) for frame in seed.frames]
        _find_repeats(context)


def _find_repeats(context: PipelineContext) -> None:
    """Mark frames that repeat the one before, as the frames stage does, so a rerun of
    OCR or objects doesn't pay for a slide twice. Without the image tools (or a frame
    file), every frame is analysed."""
    try:
        looks = [frames.look(resolve(frame.path)) for frame in context.frames]
    except Exception:  # noqa: BLE001 - an optimisation only
        logger.info("record_id=%s repeats not checked", context.request.record_id, exc_info=True)
        return
    for frame, repeat_of in zip(context.frames, frames.duplicates(looks), strict=True):
        frame.duplicate_of = repeat_of


def _quiet(_progress: int) -> None:
    """Progress of a restored stage isn't reported: it isn't being run again."""
