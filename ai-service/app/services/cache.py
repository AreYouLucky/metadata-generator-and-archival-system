"""Small key/value cache for LLM results (Redis in the containers, a dict in tests)."""

from typing import Protocol

from app.config import Settings, get_settings


class Cache(Protocol):
    def get(self, key: str) -> str | None: ...

    def set(self, key: str, value: str, ttl_seconds: int) -> None: ...


class MemoryCache:
    def __init__(self) -> None:
        self.values: dict[str, str] = {}

    def get(self, key: str) -> str | None:
        return self.values.get(key)

    def set(self, key: str, value: str, ttl_seconds: int) -> None:
        self.values[key] = value


class RedisCache:
    def __init__(self, url: str) -> None:
        import redis

        self._redis = redis.Redis.from_url(url, decode_responses=True)

    def get(self, key: str) -> str | None:
        value = self._redis.get(key)
        return value if isinstance(value, str) else None

    def set(self, key: str, value: str, ttl_seconds: int) -> None:
        self._redis.set(key, value, ex=ttl_seconds)


_memory = MemoryCache()


def get_cache(settings: Settings | None = None) -> Cache:
    settings = settings or get_settings()
    if settings.redis_url.startswith("redis"):
        return RedisCache(settings.redis_url)
    return _memory
