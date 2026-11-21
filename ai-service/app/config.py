from functools import lru_cache
from typing import Literal

from pydantic import Field, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict

ReasoningEffort = Literal["none", "minimal", "low", "medium", "high"]


class Settings(BaseSettings):
    """Service configuration, read from environment variables (or ai-service/.env)."""

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    internal_shared_secret: SecretStr = Field(default=SecretStr(""))
    signature_max_age_seconds: int = 300
    laravel_callback_base: str = "http://web:8000"
    callback_timeout_seconds: float = 10.0

    storage_root: str = "/data/storage"
    # Access copies (proxy stage) are never taller than this.
    proxy_max_height: int = Field(default=1080, ge=240, le=2160)
    redis_url: str = "redis://redis:6379/1"
    celery_result_url: str = "redis://redis:6379/2"

    # "redis" shares seen signatures across uvicorn workers; "memory" is per process.
    replay_guard: Literal["redis", "memory"] = "memory"

    # Transcription
    transcription_provider: Literal["local", "openai"] = "local"
    local_whisper_model: str = "large-v3-turbo"
    local_whisper_device: str = "cuda"
    local_whisper_compute_type: str = "int8_float16"
    # Speech chunks decoded together (faster-whisper's batched pipeline). 8 fits the 6 GB
    # card with large-v3-turbo; lower it if CUDA runs out of memory, 1 = one at a time.
    local_whisper_batch_size: int = Field(default=8, ge=1, le=32)
    openai_transcription_model: str = "whisper-1"
    # The OpenAI upload limit is 25 MB; stay under it with room for the multipart envelope.
    openai_max_upload_bytes: int = 24 * 1024 * 1024
    openai_chunk_seconds: int = 600
    openai_chunk_overlap_seconds: float = 2.0

    # LLM (see DECISIONS.md, "Token budget")
    llm_provider: Literal["openai"] = "openai"
    llm_model: str = "gpt-6.2-sol"
    # Cheaper model for the map step of long transcripts.
    llm_light_model: str = "gpt-6-luna"
    llm_reasoning_effort: ReasoningEffort = "low"
    llm_light_reasoning_effort: ReasoningEffort = "none"
    llm_max_output_tokens: int = 8000
    # Above this estimated size the transcript is summarised in chunks first (map/reduce).
    llm_max_transcript_tokens: int = 60000
    llm_map_chunk_tokens: int = 20000
    # Transcript lines are merged into blocks of about this many seconds for the prompt.
    llm_block_seconds: float = 30.0
    llm_cache_ttl_seconds: int = 60 * 60 * 24 * 30

    # Translation (Phase 2). The main model by default: Filipino and regional languages are
    # where a small model's subtitles read badly. Each batch is a few thousand tokens.
    llm_translation_model: str = "gpt-6.2-sol"
    llm_translation_reasoning_effort: ReasoningEffort = "low"
    translation_batch_lines: int = Field(default=60, ge=1, le=200)
    translation_batch_chars: int = Field(default=6000, ge=500, le=20000)

    openai_api_key: SecretStr = Field(default=SecretStr(""))
    openai_timeout_seconds: float = 180.0


@lru_cache
def get_settings() -> Settings:
    return Settings()
