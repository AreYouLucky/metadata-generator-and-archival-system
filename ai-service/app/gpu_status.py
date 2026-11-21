"""Which GPU the transcription worker can use, as seen by `/health`.

Only `ai-worker-gpu` is given the GPU (docker-compose.gpu.yml), so the API
container cannot detect it itself. A worker that finds an NVIDIA GPU publishes
its name to Redis with a short expiry and refreshes it while it runs; `/health`
reads it. If the worker stops, the entry expires and the GPU shows as missing.
"""

import logging
import shutil
import subprocess
import threading
from typing import Protocol

from app.config import Settings, get_settings

logger = logging.getLogger(__name__)

KEY = "ai:gpu-worker"
TTL_SECONDS = 90
REFRESH_SECONDS = 30


class Store(Protocol):
    def get(self, key: str) -> str | None: ...

    def set(self, key: str, value: str, ttl_seconds: int) -> None: ...

    def delete(self, key: str) -> None: ...


class MemoryStore:
    def __init__(self) -> None:
        self.values: dict[str, str] = {}

    def get(self, key: str) -> str | None:
        return self.values.get(key)

    def set(self, key: str, value: str, ttl_seconds: int) -> None:
        self.values[key] = value

    def delete(self, key: str) -> None:
        self.values.pop(key, None)


class RedisStore:
    def __init__(self, url: str) -> None:
        import redis

        # Short timeouts: /health must answer fast even when Redis is down.
        self._redis = redis.Redis.from_url(
            url, decode_responses=True, socket_timeout=0.5, socket_connect_timeout=0.5
        )

    def get(self, key: str) -> str | None:
        value = self._redis.get(key)
        return value if isinstance(value, str) else None

    def set(self, key: str, value: str, ttl_seconds: int) -> None:
        self._redis.set(key, value, ex=ttl_seconds)

    def delete(self, key: str) -> None:
        self._redis.delete(key)


_memory = MemoryStore()


def get_store(settings: Settings | None = None) -> Store:
    settings = settings or get_settings()
    if settings.redis_url.startswith("redis"):
        return RedisStore(settings.redis_url)
    return _memory


def detect_gpu_name() -> str | None:
    """The first NVIDIA GPU's name, or None when there is no usable GPU."""
    executable = shutil.which("nvidia-smi")
    if executable is None:
        return None

    try:
        result = subprocess.run(  # noqa: S603 - fixed arguments, resolved executable
            [executable, "--query-gpu=name", "--format=csv,noheader"],
            capture_output=True,
            text=True,
            timeout=10,
            check=True,
        )
    except (OSError, subprocess.SubprocessError):
        return None

    names = [line.strip() for line in result.stdout.splitlines() if line.strip()]
    return names[0] if names else None


def read_worker_gpu(store: Store | None = None) -> str | None:
    """The GPU name a running worker published, or None (also when Redis is down)."""
    try:
        return (store or get_store()).get(KEY)
    except Exception:  # noqa: BLE001 - health must never fail because of Redis
        logger.warning("Could not read the GPU worker status", exc_info=True)
        return None


def publish(name: str, store: Store | None = None) -> None:
    try:
        (store or get_store()).set(KEY, name, TTL_SECONDS)
    except Exception:  # noqa: BLE001 - retried on the next refresh
        logger.warning("Could not publish the GPU worker status", exc_info=True)


def withdraw(store: Store | None = None) -> None:
    try:
        (store or get_store()).delete(KEY)
    except Exception:  # noqa: BLE001 - the entry expires on its own anyway
        logger.warning("Could not withdraw the GPU worker status", exc_info=True)


class Heartbeat:
    """Keeps the worker's GPU entry fresh until stopped."""

    def __init__(self, name: str, store: Store | None = None) -> None:
        self._name = name
        self._store = store
        self._stop = threading.Event()
        self._thread = threading.Thread(target=self._run, name="gpu-heartbeat", daemon=True)

    def start(self) -> None:
        self._thread.start()

    def stop(self) -> None:
        self._stop.set()
        withdraw(self._store)

    def _run(self) -> None:
        while True:
            publish(self._name, self._store)
            if self._stop.wait(REFRESH_SECONDS):
                return
