from app.config import Settings, get_settings
from app.providers.llm.base import (
    ImageInput,
    LlmError,
    LlmProvider,
    LlmUsage,
    VisionLlmProvider,
)


def get_llm_provider(settings: Settings | None = None) -> LlmProvider:
    return get_vision_llm_provider(settings)


def get_vision_llm_provider(settings: Settings | None = None) -> VisionLlmProvider:
    settings = settings or get_settings()
    from app.providers.llm.openai_llm import OpenAILlmProvider

    return OpenAILlmProvider(settings)


__all__ = [
    "ImageInput",
    "LlmError",
    "LlmProvider",
    "LlmUsage",
    "VisionLlmProvider",
    "get_llm_provider",
    "get_vision_llm_provider",
]
