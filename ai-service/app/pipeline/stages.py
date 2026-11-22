"""The work of each Phase 1 stage. Pure functions over the pipeline context, so they can be
tested without Celery or Laravel; app.pipeline.tasks wires them into the chain."""

import json
import logging
import subprocess  # noqa: S404 - only for its exception types
from collections.abc import Callable
from pathlib import Path
from typing import Any

from app.config import get_settings
from app.pipeline import audio, checksum, probe, proxy
from app.providers.transcription import get_transcription_provider
from app.schemas.pipeline import PipelineContext
from app.services.metadata import MetadataGenerator
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
    result = get_transcription_provider().transcribe(
        resolve(context.audio_path), context.duration, on_progress
    )
    context.segments = result.segments
    context.language = result.language
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


def run_generate_metadata(context: PipelineContext, on_progress: Progress) -> dict[str, Any]:
    if not context.segments:
        raise SkipStage("Nothing was said in the video, so there is nothing to describe.")
    options = context.request.options
    on_progress(10)
    result = MetadataGenerator().generate(
        context.segments,
        options.preset,
        language=context.language,
        custom_prompt=options.custom_prompt,
        title_count=options.title_count,
        duration=context.duration,
    )
    return {
        "preset": options.preset.name,
        "custom_prompt": options.custom_prompt,
        **result.model_dump(),
    }
