"""Celery app.

Two workers (see docker-compose.yml):
    celery -A app.worker worker -Q celery            # probing, checksums, audio, LLM calls
    celery -A app.worker worker -Q gpu -c 1          # transcription, one at a time on the GPU
"""

import threading
from typing import Any

import httpx
from celery import Celery
from celery.signals import worker_ready, worker_shutdown

from app import gpu_status
from app.callbacks import post_to_laravel
from app.config import get_settings

settings = get_settings()

celery_app = Celery(
    "ai_service",
    broker=settings.redis_url,
    backend=settings.celery_result_url,
    include=["app.pipeline.tasks"],
)
celery_app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    task_acks_late=True,
    worker_prefetch_multiplier=1,
    result_expires=3600,
    broker_connection_retry_on_startup=True,
)


@celery_app.task(
    name="system.send_ping_callback",
    autoretry_for=(httpx.HTTPError,),
    retry_backoff=True,
    max_retries=3,
)
def send_ping_callback(ping_id: str) -> dict[str, Any]:
    """Phase 0 round trip: call Laravel's signed /internal/ping from the worker."""
    response = post_to_laravel("/internal/ping", {"ping_id": ping_id, "source": "ai-worker"})
    return {"ping_id": ping_id, "status": response.status_code}


_gpu_heartbeat: gpu_status.Heartbeat | None = None


@worker_ready.connect
def prefetch_whisper_model(sender: Any = None, **_: Any) -> None:
    """The transcription worker downloads its model in the background at start-up,
    so no job ever waits inside the download (see providers/transcription/local.py)."""
    if settings.transcription_provider != "local":
        return
    if not str(getattr(sender, "hostname", "")).startswith("gpu@"):
        return

    from app.providers.transcription.local import prefetch_model

    threading.Thread(target=prefetch_model, name="whisper-prefetch", daemon=True).start()


@worker_ready.connect
def announce_gpu(**_: Any) -> None:
    """A worker that can see an NVIDIA GPU tells /health about it (see gpu_status)."""
    global _gpu_heartbeat

    name = gpu_status.detect_gpu_name()
    if name is not None:
        _gpu_heartbeat = gpu_status.Heartbeat(name)
        _gpu_heartbeat.start()


@worker_shutdown.connect
def withdraw_gpu(**_: Any) -> None:
    if _gpu_heartbeat is not None:
        _gpu_heartbeat.stop()
