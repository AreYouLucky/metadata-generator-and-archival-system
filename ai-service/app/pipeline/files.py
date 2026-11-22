"""Celery tasks on one stored file (POST /jobs/ingest-file, POST /jobs/fixity).

Callbacks, signed like every other:
    /internal/record-files/{file}/results/checksum   {md5, sha256, file_size}
    /internal/record-files/{file}/results/probe      probe summary + raw_probe
    /internal/record-files/{file}/results/failed     {message}
    /internal/record-files/{file}/fixity             {md5, sha256, triggered_by} or {error}
    /internal/records/{record}/audio-export          {format, path, file_size} or {format, error}
    /internal/records/{record}/clips/{clip}          {path, file_size, md5, sha256, probe}
                                                     or {error}
"""

import logging
from typing import Any

from app.callbacks import post_with_retry
from app.pipeline import audio, checksum, clips, probe
from app.pipeline.tasks import user_message
from app.schemas.files import AudioExportRequest, ClipRequest, FixityRequest, IngestFileRequest
from app.storage import resolve
from app.worker import celery_app

logger = logging.getLogger(__name__)


def run_ingest(request_data: dict[str, Any]) -> None:
    request = IngestFileRequest.model_validate(request_data)
    base = f"/internal/record-files/{request.record_file_id}/results"
    try:
        path = resolve(request.path)
        # Checksums first, before anything else reads the file (as for masters).
        post_with_retry(f"{base}/checksum", dict(checksum.compute(path)))
        raw_ffprobe = probe.run_ffprobe(path)
        raw_mediainfo = probe.run_mediainfo(path)
        summary = probe.summarise(raw_ffprobe, raw_mediainfo)
        post_with_retry(
            f"{base}/probe",
            {**summary, "raw_probe": {"ffprobe": raw_ffprobe, "mediainfo": raw_mediainfo}},
        )
    except Exception as error:  # noqa: BLE001 - reported to Laravel
        logger.exception("record_id=%s file ingest failed", request.record_id)
        post_with_retry(f"{base}/failed", {"message": user_message("file check", error)})


def run_fixity(request_data: dict[str, Any]) -> None:
    request = FixityRequest.model_validate(request_data)
    path = f"/internal/record-files/{request.record_file_id}/fixity"
    try:
        sums = checksum.compute(resolve(request.path))
    except FileNotFoundError:
        post_with_retry(path, {"triggered_by": request.triggered_by, "error": "missing"})
        return
    except Exception as error:  # noqa: BLE001 - reported to Laravel
        logger.exception("record_file_id=%s fixity failed", request.record_file_id)
        post_with_retry(
            path,
            {"triggered_by": request.triggered_by, "error": user_message("fixity check", error)},
        )
        return
    post_with_retry(
        path,
        {"triggered_by": request.triggered_by, "md5": sums["md5"], "sha256": sums["sha256"]},
    )


def run_audio_export(request_data: dict[str, Any]) -> None:
    request = AudioExportRequest.model_validate(request_data)
    path = f"/internal/records/{request.record_id}/audio-export"
    try:
        target = audio.export(
            resolve(request.video_path),
            resolve(request.output_path),
            request.format,
            request.duration,
        )
    except Exception as error:  # noqa: BLE001 - reported to Laravel
        logger.exception("record_id=%s audio export failed", request.record_id)
        post_with_retry(
            path, {"format": request.format, "error": user_message("audio export", error)}
        )
        return
    post_with_retry(
        path,
        {
            "format": request.format,
            "path": request.output_path,
            "file_size": target.stat().st_size,
        },
    )


def run_clip(request_data: dict[str, Any]) -> None:
    """Cut, then checksum and probe the clip like any other file (PROJECT_SPEC section 6)."""
    request = ClipRequest.model_validate(request_data)
    path = f"/internal/records/{request.record_id}/clips/{request.clip_id}"
    try:
        target = clips.cut(
            resolve(request.video_path),
            resolve(request.output_path),
            request.start,
            request.end,
            request.max_height,
        )
        sums = checksum.compute(target)
        summary = probe.summarise(probe.run_ffprobe(target), probe.run_mediainfo(target))
    except Exception as error:  # noqa: BLE001 - reported to Laravel
        logger.exception("record_id=%s clip %s failed", request.record_id, request.clip_id)
        post_with_retry(path, {"error": user_message("clip", error)})
        return
    post_with_retry(
        path,
        {
            "path": request.output_path,
            "file_size": sums["file_size"],
            "md5": sums["md5"],
            "sha256": sums["sha256"],
            "probe": summary,
        },
    )


@celery_app.task(name="files.ingest", soft_time_limit=2 * 60 * 60)
def ingest_task(request_data: dict[str, Any]) -> None:
    run_ingest(request_data)


@celery_app.task(name="files.fixity", soft_time_limit=2 * 60 * 60)
def fixity_task(request_data: dict[str, Any]) -> None:
    run_fixity(request_data)


@celery_app.task(name="files.export_audio", soft_time_limit=60 * 60)
def export_audio_task(request_data: dict[str, Any]) -> None:
    run_audio_export(request_data)


@celery_app.task(name="files.clip", soft_time_limit=40 * 60)
def clip_task(request_data: dict[str, Any]) -> None:
    run_clip(request_data)


def start_clip(request: ClipRequest) -> str:
    return str(clip_task.delay(request.model_dump()).id)


def start_ingest(request: IngestFileRequest) -> str:
    return str(ingest_task.delay(request.model_dump()).id)


def start_fixity(request: FixityRequest) -> str:
    return str(fixity_task.delay(request.model_dump()).id)


def start_audio_export(request: AudioExportRequest) -> str:
    return str(export_audio_task.delay(request.model_dump()).id)
