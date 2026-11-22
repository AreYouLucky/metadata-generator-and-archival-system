"""Celery task for one transcript translation (POST /jobs/translate).

Reports to Laravel on the signed callbacks
    /internal/records/{record}/translations/{transcript}/progress   running | failed
    /internal/records/{record}/translations/{transcript}/result     the translated lines
"""

import logging
import time
from typing import Any

import httpx

from app.callbacks import post_to_laravel, post_with_retry
from app.pipeline.tasks import user_message
from app.schemas.translation import TranslateRequest
from app.services.translation import Translator
from app.worker import celery_app

logger = logging.getLogger(__name__)


def callback_path(request: TranslateRequest, kind: str) -> str:
    return f"/internal/records/{request.record_id}/translations/{request.transcript_id}/{kind}"


class TranslationProgress:
    """Best-effort progress: at most one callback every 2 seconds (100 always goes out)."""

    def __init__(self, request: TranslateRequest, min_interval: float = 2.0) -> None:
        self.path = callback_path(request, "progress")
        self.min_interval = min_interval
        self._last_sent = 0.0

    def __call__(self, progress: int) -> None:
        now = time.monotonic()
        if now - self._last_sent < self.min_interval and progress < 100:
            return
        self._last_sent = now
        try:
            post_to_laravel(self.path, {"status": "running", "progress": min(99, progress)})
        except httpx.HTTPError:
            pass


def run_translation(request_data: dict[str, Any]) -> None:
    request = TranslateRequest.model_validate(request_data)
    log = logging.LoggerAdapter(logger, {"record_id": request.record_id})
    post_with_retry(callback_path(request, "progress"), {"status": "running", "progress": 0})

    try:
        result = Translator().translate(
            request.lines,
            request.target_language_name,
            request.source_language,
            on_progress=TranslationProgress(request),
        )
    except Exception as error:  # noqa: BLE001 - reported to Laravel as a failed translation
        log.exception("record_id=%s translation failed", request.record_id)
        post_with_retry(
            callback_path(request, "progress"),
            {"status": "failed", "progress": 0, "message": user_message("translation", error)},
        )
        return

    post_with_retry(
        callback_path(request, "result"),
        {
            "model": result.model,
            "lines": [line.model_dump() for line in result.lines],
            "kept": result.kept,
            "usage": [usage.model_dump() for usage in result.usage],
        },
    )
    log.info(
        "record_id=%s translated %d lines into %s",
        request.record_id,
        len(result.lines),
        request.target_language,
    )


@celery_app.task(name="translation.translate", soft_time_limit=2 * 60 * 60)
def translate_task(request_data: dict[str, Any]) -> None:
    run_translation(request_data)


def start_translation(request: TranslateRequest) -> str:
    return str(translate_task.delay(request.model_dump()).id)
