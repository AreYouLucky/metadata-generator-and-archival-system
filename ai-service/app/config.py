from functools import lru_cache
from typing import Literal

from pydantic import Field, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Service configuration, read from environment variables (or ai-service/.env)."""

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    internal_shared_secret: SecretStr = Field(default=SecretStr(""))
    signature_max_age_seconds: int = 300
    laravel_callback_base: str = "http://web:8000"
    callback_timeout_seconds: float = 10.0

    storage_root: str = "/data/storage"
    redis_url: str = "redis://redis:6379/1"
    celery_result_url: str = "redis://redis:6379/2"

    # "redis" shares seen signatures across uvicorn workers; "memory" is per process.
    replay_guard: Literal["redis", "memory"] = "memory"


@lru_cache
def get_settings() -> Settings:
    return Settings()
