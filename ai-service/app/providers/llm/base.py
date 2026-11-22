from typing import Literal, Protocol, TypeVar

from pydantic import BaseModel

from app.config import ReasoningEffort

T = TypeVar("T", bound=BaseModel)


class LlmUsage(BaseModel):
    model: str
    input_tokens: int = 0
    cached_tokens: int = 0
    output_tokens: int = 0


class LlmError(RuntimeError):
    pass


class ImageInput(BaseModel):
    """An image sent with the last message, as a data URL (never a public link)."""

    data_url: str
    detail: Literal["low", "high", "auto"] = "low"


class LlmProvider(Protocol):
    def complete(
        self,
        *,
        model: str,
        instructions: str,
        messages: list[str],
        schema: type[T],
        reasoning_effort: ReasoningEffort,
        max_output_tokens: int,
    ) -> tuple[T, LlmUsage]:
        """One structured call. `instructions` is the static prefix (cache-friendly);
        `messages` follow in order, most stable first."""
        ...


class VisionLlmProvider(Protocol):
    def complete(
        self,
        *,
        model: str,
        instructions: str,
        messages: list[str],
        schema: type[T],
        reasoning_effort: ReasoningEffort,
        max_output_tokens: int,
        images: list[ImageInput] | None = None,
    ) -> tuple[T, LlmUsage]:
        """Like LlmProvider.complete, with images attached to the last message."""
        ...
