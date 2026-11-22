"""Cancelling a running pipeline (POST /jobs/{job_id}/cancel).

Each pipeline run has its own id (the job id Laravel stores). Cancelling writes a flag
for that id to Redis; every stage checks it before it starts, while it reports progress
and before it reports its result, so the rest of the chain stops without calling Laravel
or a paid provider again. A retry is a new run with a new id, so an old flag never stops
it. Stages already running only stop at their next progress tick (or when they finish).
"""

import logging
import time
import uuid
from collections.abc import Callable
from functools import lru_cache
from typing import Protocol

from app.config import get_settings

logger = logging.getLogger(__name__)

KEY_PREFIX = "pipeline:cancelled:"
# Longer than a run can take, GPU queue included; the flag is useless after that.
TTL_SECONDS = 7 * 24 * 60 * 60
JOB_ID_PATTERN = r"^[A-Za-z0-9-]{1,64}$"


class PipelineCancelled(Exception):
    """Raised inside a stage when its run was cancelled; never reported to Laravel."""


class Store(Protocol):
    def mark(self, run_id: str) -> None: ...

    def is_marked(self, run_id: str) -> bool: ...


class MemoryStore:
    """For tests and a single process (REDIS_URL=memory://)."""

    def __init__(self) -> None:
        self.cancelled: set[str] = set()

    def mark(self, run_id: str) -> None:
        self.cancelled.add(run_id)

    def is_marked(self, run_id: str) -> bool:
        return run_id in self.cancelled


class RedisStore:
    def __init__(self, url: str) -> None:
        import redis

        self._redis = redis.Redis.from_url(url, socket_timeout=2, socket_connect_timeout=2)

    def mark(self, run_id: str) -> None:
        self._redis.set(KEY_PREFIX + run_id, 1, ex=TTL_SECONDS)

    def is_marked(self, run_id: str) -> bool:
        return bool(self._redis.exists(KEY_PREFIX + run_id))


@lru_cache
def get_store() -> Store:
    url = get_settings().redis_url
    return RedisStore(url) if url.startswith("redis") else MemoryStore()


def new_run_id() -> str:
    return uuid.uuid4().hex


def cancel(run_id: str) -> None:
    get_store().mark(run_id)


def is_cancelled(run_id: str | None) -> bool:
    """False when Redis can't be reached: a missed cancel only wastes work, while a
    wrongly stopped pipeline would lose the encoder's results."""
    if not run_id:
        return False
    try:
        return get_store().is_marked(run_id)
    except Exception:  # noqa: BLE001 - see docstring
        logger.warning("Could not read the cancel flag for run %s", run_id, exc_info=True)
        return False


class CancellableProgress:
    """Wraps a stage's progress callback: checks the cancel flag at most every
    `interval` seconds and raises PipelineCancelled once the run is cancelled."""

    def __init__(
        self, run_id: str | None, report: Callable[..., None], interval: float = 5.0
    ) -> None:
        self.run_id = run_id
        self.report = report
        self.interval = interval
        self._last_check = time.monotonic()

    def __call__(self, progress: int, message: str | None = None) -> None:
        now = time.monotonic()
        if now - self._last_check >= self.interval:
            self._last_check = now
            if is_cancelled(self.run_id):
                raise PipelineCancelled
        if message is None:
            self.report(progress)
        else:
            self.report(progress, message)
