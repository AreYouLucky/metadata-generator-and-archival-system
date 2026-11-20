"""Celery app. Start with: celery -A app.worker worker --loglevel=info"""

from typing import Any

import httpx
from celery import Celery

from app.callbacks import post_to_laravel
from app.config import get_settings

settings = get_settings()

celery_app = Celery("ai_service", broker=settings.redis_url, backend=settings.celery_result_url)
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
