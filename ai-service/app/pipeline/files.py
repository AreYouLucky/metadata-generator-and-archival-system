"""Celery tasks on one stored file (POST /jobs/ingest-file, POST /jobs/fixity).

Callbacks, signed like every other:
    /internal/record-files/{file}/results/checksum   {md5, sha256, file_size}
    /internal/record-files/{file}/results/probe      probe summary + raw_probe
    /internal/record-files/{file}/results/failed     {message}
    /internal/record-files/{file}/fixity             {md5, sha256, triggered_by} or {error}
    /internal/records/{record}/audio-export          {format, path, file_size} or {format, error}
"""

import logging
from typing import Any

from app.callbacks import post_with_retry
from app.pipeline import audio, checksum, probe
from app.pipeline.tasks import user_message
from app.schemas.files import AudioExportRequest, FixityRequest, IngestFileRequest
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


@celery_app.task(name="files.ingest", soft_time_limit=2 * 60 * 60)
def ingest_task(request_data: dict[str, Any]) -> None:
    run_ingest(request_data)


@celery_app.task(name="files.fixity", soft_time_limit=2 * 60 * 60)
def fixity_task(request_data: dict[str, Any]) -> None:
    run_fixity(request_data)


@celery_app.task(name="files.export_audio", soft_time_limit=60 * 60)
def export_audio_task(request_data: dict[str, Any]) -> None:
    run_audio_export(request_data)


def start_ingest(request: IngestFileRequest) -> str:
    return str(ingest_task.delay(request.model_dump()).id)


def start_fixity(request: FixityRequest) -> str:
    return str(fixity_task.delay(request.model_dump()).id)


def start_audio_export(request: AudioExportRequest) -> str:
    return str(export_audio_task.delay(request.model_dump()).id)
