from typing import Any, Literal, Protocol, TypeVar

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


class FunctionSpec(BaseModel):
    """A function tool offered to the model (strict JSON schema parameters)."""

    name: str
    description: str
    parameters: dict[str, Any]


class TurnMessage(BaseModel):
    role: Literal["user", "assistant"]
    content: str


class TurnToolCall(BaseModel):
    """A tool call the model made earlier in this turn (replayed, the service is stateless)."""

    call_id: str
    name: str
    arguments: str


class TurnToolOutput(BaseModel):
    call_id: str
    output: str


ChatTurn = TurnMessage | TurnToolCall | TurnToolOutput


class RequestedToolCall(BaseModel):
    call_id: str
    name: str
    arguments: dict[str, Any]


ToolChoice = Literal["required", "auto", "none"]


class ToolLlmProvider(Protocol):
    def converse(
        self,
        *,
        model: str,
        instructions: str,
        turns: list[ChatTurn],
        tools: list[FunctionSpec],
        tool_choice: ToolChoice,
        schema: type[T],
        reasoning_effort: ReasoningEffort,
        max_output_tokens: int,
    ) -> tuple[list[RequestedToolCall] | T, LlmUsage]:
        """One step of a tool loop: the tool calls the model wants next, or its structured
        answer. With `tool_choice="none"` it always answers."""
        ...
