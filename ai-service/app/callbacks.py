"""Signed calls from the AI service back to Laravel's /internal routes."""

import json
import time
from typing import Any

import httpx
from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

from app.config import Settings, get_settings
from app.security import signed_headers


def encode(payload: dict[str, Any]) -> bytes:
    """Compact JSON: the exact bytes that are signed are the bytes that are sent."""
    return json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode()


def post_to_laravel(
    path: str,
    payload: dict[str, Any],
    settings: Settings | None = None,
    transport: httpx.BaseTransport | None = None,
) -> httpx.Response:
    """POST a signed JSON body to Laravel. Raises on network errors and non-2xx answers."""
    settings = settings or get_settings()
    body = encode(payload)
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json",
        **signed_headers(settings.internal_shared_secret.get_secret_value(), body),
    }
    with httpx.Client(
        base_url=settings.laravel_callback_base,
        timeout=settings.callback_timeout_seconds,
        transport=transport,
    ) as client:
        response = client.post(path, content=body, headers=headers)
    response.raise_for_status()
    return response


def report_progress(
    record_id: str,
    stage: str,
    status: str,
    progress: int = 0,
    message: str | None = None,
    retry_on_error: bool = True,
) -> None:
    """POST /internal/records/{id}/progress. Status: running | failed | skipped.

    Status changes are retried; throttled progress ticks are best effort."""
    send = _post_with_retry if retry_on_error else post_to_laravel
    send(
        f"/internal/records/{record_id}/progress",
        {
            "stage": stage,
            "status": status,
            "progress": max(0, min(100, progress)),
            "message": message[:1000] if message else None,
        },
    )


def report_result(record_id: str, stage: str, result: dict[str, Any]) -> None:
    """POST /internal/records/{id}/results/{stage}: marks the stage done and stores its output."""
    _post_with_retry(f"/internal/records/{record_id}/results/{stage}", result)


def _is_retryable(error: BaseException) -> bool:
    """Network errors, 429 and 5xx are worth retrying; a 4xx means the payload is wrong."""
    if isinstance(error, httpx.HTTPStatusError):
        return error.response.status_code == 429 or error.response.status_code >= 500
    return isinstance(error, httpx.TransportError)


@retry(
    retry=retry_if_exception(_is_retryable),
    stop=stop_after_attempt(6),
    wait=wait_exponential(multiplier=1, max=30),
    reraise=True,
)
def _post_with_retry(path: str, payload: dict[str, Any]) -> None:
    # Each attempt is signed again with a fresh timestamp, so the replay guard accepts it.
    post_to_laravel(path, payload)


class ProgressReporter:
    """Throttled progress for long stages: at most one callback per `min_step` percent
    and per `min_interval` seconds, so a long transcription doesn't flood Laravel."""

    def __init__(
        self, record_id: str, stage: str, min_step: int = 5, min_interval: float = 2.0
    ) -> None:
        self.record_id = record_id
        self.stage = stage
        self.min_step = min_step
        self.min_interval = min_interval
        self._last_progress = -100
        self._last_sent = 0.0

    def __call__(self, progress: int, message: str | None = None) -> None:
        now = time.monotonic()
        if progress - self._last_progress < self.min_step and progress < 100:
            return
        if now - self._last_sent < self.min_interval and progress < 100:
            return
        self._last_progress = progress
        self._last_sent = now
        try:
            report_progress(
                self.record_id, self.stage, "running", progress, message, retry_on_error=False
            )
        except httpx.HTTPError:
            # Progress is best effort; the stage result is what must arrive.
            pass
