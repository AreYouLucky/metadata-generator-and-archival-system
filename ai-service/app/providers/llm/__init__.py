from app.config import Settings, get_settings
from app.providers.llm.base import LlmError, LlmProvider, LlmUsage


def get_llm_provider(settings: Settings | None = None) -> LlmProvider:
    settings = settings or get_settings()
    from app.providers.llm.openai_llm import OpenAILlmProvider

    return OpenAILlmProvider(settings)


__all__ = ["LlmError", "LlmProvider", "LlmUsage", "get_llm_provider"]
