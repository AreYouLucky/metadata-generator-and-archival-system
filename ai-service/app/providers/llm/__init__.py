from app.config import Settings, get_settings
from app.providers.llm.base import (
    ChatTurn,
    FunctionSpec,
    ImageInput,
    LlmError,
    LlmProvider,
    LlmUsage,
    RequestedToolCall,
    ToolChoice,
    ToolLlmProvider,
    TurnMessage,
    TurnToolCall,
    TurnToolOutput,
    VisionLlmProvider,
)


def get_llm_provider(settings: Settings | None = None) -> LlmProvider:
    return get_vision_llm_provider(settings)


def get_tool_llm_provider(settings: Settings | None = None) -> ToolLlmProvider:
    settings = settings or get_settings()
    from app.providers.llm.openai_llm import OpenAILlmProvider

    return OpenAILlmProvider(settings)


def get_vision_llm_provider(settings: Settings | None = None) -> VisionLlmProvider:
    settings = settings or get_settings()
    from app.providers.llm.openai_llm import OpenAILlmProvider

    return OpenAILlmProvider(settings)


__all__ = [
    "ChatTurn",
    "FunctionSpec",
    "ImageInput",
    "LlmError",
    "LlmProvider",
    "LlmUsage",
    "RequestedToolCall",
    "ToolChoice",
    "ToolLlmProvider",
    "TurnMessage",
    "TurnToolCall",
    "TurnToolOutput",
    "VisionLlmProvider",
    "get_llm_provider",
    "get_tool_llm_provider",
    "get_vision_llm_provider",
]
