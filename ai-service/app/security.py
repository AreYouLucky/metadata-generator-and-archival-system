"""HMAC signing shared with Laravel (app/Services/InternalSignature.php).

    X-Timestamp: unix seconds
    X-Signature: sha256=hex(HMAC_SHA256(secret, timestamp + "." + raw_body))

Requests older (or further in the future) than the max age are rejected, and each
signature is accepted only once inside that window. Verification fails closed.
"""

import hashlib
import hmac
import threading
import time
from typing import Annotated, Protocol

from fastapi import Depends, HTTPException, Request, status

from app.config import Settings, get_settings

TIMESTAMP_HEADER = "X-Timestamp"
SIGNATURE_HEADER = "X-Signature"


def _secret_bytes(secret: str) -> bytes:
    if not secret:
        raise RuntimeError("INTERNAL_SHARED_SECRET is not set.")
    return secret.encode()


def sign(secret: str, timestamp: str, body: bytes) -> str:
    digest = hmac.new(_secret_bytes(secret), timestamp.encode() + b"." + body, hashlib.sha256)
    return "sha256=" + digest.hexdigest()


def signed_headers(secret: str, body: bytes, now: int | None = None) -> dict[str, str]:
    timestamp = str(int(time.time()) if now is None else now)
    return {TIMESTAMP_HEADER: timestamp, SIGNATURE_HEADER: sign(secret, timestamp, body)}


def verify(
    secret: str,
    timestamp: str | None,
    signature: str | None,
    body: bytes,
    max_age_seconds: int,
    now: int | None = None,
) -> bool:
    if timestamp is None or signature is None or not timestamp.isdigit():
        return False
    current = int(time.time()) if now is None else now
    if abs(current - int(timestamp)) > max_age_seconds:
        return False
    return hmac.compare_digest(sign(secret, timestamp, body), signature)


class ReplayGuard(Protocol):
    def first_use(self, signature: str, ttl_seconds: int) -> bool: ...


class MemoryReplayGuard:
    """Per-process guard. Enough for a single uvicorn worker and for tests."""

    def __init__(self) -> None:
        self._seen: dict[str, float] = {}
        self._lock = threading.Lock()

    def first_use(self, signature: str, ttl_seconds: int) -> bool:
        now = time.monotonic()
        with self._lock:
            self._seen = {key: expiry for key, expiry in self._seen.items() if expiry > now}
            if signature in self._seen:
                return False
            self._seen[signature] = now + ttl_seconds
            return True


class RedisReplayGuard:
    def __init__(self, url: str) -> None:
        import redis

        self._redis = redis.Redis.from_url(url)

    def first_use(self, signature: str, ttl_seconds: int) -> bool:
        key = "ai-service:signature:" + hashlib.sha256(signature.encode()).hexdigest()
        return bool(self._redis.set(key, 1, nx=True, ex=ttl_seconds))


_memory_guard = MemoryReplayGuard()


def get_replay_guard(settings: Annotated[Settings, Depends(get_settings)]) -> ReplayGuard:
    if settings.replay_guard == "redis":
        return RedisReplayGuard(settings.redis_url)
    return _memory_guard


async def require_signature(
    request: Request,
    settings: Annotated[Settings, Depends(get_settings)],
    guard: Annotated[ReplayGuard, Depends(get_replay_guard)],
) -> None:
    """FastAPI dependency for every route Laravel calls (except /health)."""
    signature = request.headers.get(SIGNATURE_HEADER)
    is_valid = verify(
        settings.internal_shared_secret.get_secret_value(),
        request.headers.get(TIMESTAMP_HEADER),
        signature,
        await request.body(),
        settings.signature_max_age_seconds,
    )
    if not is_valid or signature is None:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid signature.")
    if not guard.first_use(signature, settings.signature_max_age_seconds * 2):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid signature.")
