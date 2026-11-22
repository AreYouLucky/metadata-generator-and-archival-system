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
    transcription_provider: Literal["local", "openai", "assemblyai"] = "local"
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

    # Visual analysis (Phase 5). Frames: scene starts plus one every FRAME_INTERVAL_SEC,
    # at most MAX_FRAMES (the interval grows for long videos), at most this tall.
    frame_interval_sec: float = Field(default=10.0, ge=1, le=600)
    max_frames: int = Field(default=60, ge=1, le=200)
    frame_max_height: int = Field(default=720, ge=240, le=2160)
    # PySceneDetect ContentDetector threshold (its default); lower finds more cuts.
    scene_threshold: float = Field(default=27.0, gt=0, le=255)
    max_shots: int = Field(default=2000, ge=1, le=5000)
    # Frame descriptions: the cheap model, small low-detail images, several per call.
    vision_model: str = "gpt-6-luna"
    vision_reasoning_effort: ReasoningEffort = "none"
    vision_batch_frames: int = Field(default=8, ge=1, le=20)
    vision_image_size: int = Field(default=512, ge=128, le=2048)
    vision_detail: Literal["low", "high", "auto"] = "low"
    vision_max_output_tokens: int = 2000
    # EasyOCR language codes (comma-separated); "tl" is Tagalog/Filipino.
    ocr_languages: str = "en,tl"
    ocr_min_confidence: float = Field(default=0.25, ge=0, le=1)
    # Ultralytics YOLO weights, downloaded into models_dir on first use.
    object_model: str = "yolo11n.pt"
    object_min_confidence: float = Field(default=0.35, ge=0, le=1)
    # Where OCR and object-detection models are kept (a volume in Docker).
    models_dir: str = "/srv/models"
    # On-screen content sent with the transcript to the metadata model, at most.
    visual_context_max_chars: int = Field(default=6000, ge=500, le=30000)

    # AssemblyAI (Phase 6): optional hosted transcription and/or diarization. The audio is
    # uploaded to AssemblyAI. Models in order of preference (their fallback list).
    assemblyai_api_key: SecretStr = Field(default=SecretStr(""))
    assemblyai_base_url: str = "https://api.assemblyai.com"
    assemblyai_speech_models: str = "assemblyai-stt-3-7,universal-2"
    assemblyai_poll_seconds: float = Field(default=5.0, ge=1, le=60)
    # Give up waiting for a transcript after this long.
    assemblyai_max_wait_seconds: int = Field(default=4 * 60 * 60, ge=60)

    # Speaker diarization (Phase 6): pyannote on this machine (needs HF_TOKEN and the
    # model's terms accepted on Hugging Face), or AssemblyAI. pyannote runs on the CPU by
    # default so the 6 GB GPU stays with Whisper; "cuda" sends the stage to the GPU queue
    # (needs a CUDA build of torch in the image).
    diarization_provider: Literal["pyannote", "assemblyai"] = "pyannote"
    diarization_model: str = "pyannote/speaker-diarization-community-1"
    diarization_device: Literal["cpu", "cuda"] = "cpu"
    hf_token: SecretStr = Field(default=SecretStr(""))

    # URL import with yt-dlp (Phase 6). Only yt-dlp's site extractors are allowed: the
    # generic one would fetch any address (direct file links), so it is off unless asked.
    import_allow_generic: bool = False
    import_max_height: int = Field(default=1080, ge=240, le=4320)
    import_socket_timeout_seconds: float = Field(default=30.0, ge=5, le=300)

    # Chat with the video (Phase 7). The model only calls the function tools Laravel lists
    # and answers from their results; Laravel runs the tools (this service has no database).
    llm_chat_model: str = "gpt-6.2-sol"
    llm_chat_reasoning_effort: ReasoningEffort = "low"
    llm_chat_max_output_tokens: int = Field(default=2000, ge=200, le=8000)
    # Transcript passages for semantic search. The size is chosen by Laravel per request
    # (EMBEDDING_DIMENSIONS), so both sides always agree.
    embedding_provider: Literal["openai"] = "openai"
    embedding_model: str = "text-embedding-3-small"

    openai_api_key: SecretStr = Field(default=SecretStr(""))
    openai_timeout_seconds: float = 180.0


@lru_cache
def get_settings() -> Settings:
    return Settings()
