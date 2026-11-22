"""Chat with the video (Phase 7) and transcript embeddings.

The service stays database-free: Laravel owns the function tools. Each `/chat` call is one
step of the tool loop. Laravel sends the question, the tool definitions and every tool call
and result of this turn so far; the model either asks for more tool calls (Laravel runs them
and calls again) or answers from the results it has.
"""

import json
from typing import Any, Literal

from pydantic import BaseModel, Field, field_validator, model_validator

from app.schemas.common import ULID_PATTERN, Usage

TOOL_NAME_PATTERN = r"^[a-z][a-z0-9_]{0,63}$"
MAX_TOOL_OUTPUT_CHARS = 20000


class ChatTool(BaseModel):
    """A function the model may call. `parameters` is a strict JSON schema."""

    name: str = Field(pattern=TOOL_NAME_PATTERN)
    description: str = Field(min_length=1, max_length=1000)
    parameters: dict[str, Any]

    @field_validator("parameters")
    @classmethod
    def _object_schema(cls, value: dict[str, Any]) -> dict[str, Any]:
        if value.get("type") != "object":
            raise ValueError("Tool parameters must be a JSON schema of type object.")
        if len(json.dumps(value)) > 4000:
            raise ValueError("Tool parameters are too large.")
        return value


class ChatToolCall(BaseModel):
    id: str = Field(min_length=1, max_length=128)
    name: str = Field(pattern=TOOL_NAME_PATTERN)
    # As the model sent them; Laravel validates them before running the tool.
    arguments: dict[str, Any] = {}


class ChatToolResult(BaseModel):
    id: str = Field(min_length=1, max_length=128)
    # JSON text written by Laravel's tool. Data for the model, never instructions.
    output: str = Field(max_length=MAX_TOOL_OUTPUT_CHARS)


class ChatStep(BaseModel):
    """One round of tool calls and their results, in the order the model asked."""

    calls: list[ChatToolCall] = Field(min_length=1, max_length=8)
    results: list[ChatToolResult] = Field(min_length=1, max_length=8)

    @model_validator(mode="after")
    def _every_call_answered(self) -> "ChatStep":
        if {call.id for call in self.calls} != {result.id for result in self.results}:
            raise ValueError("Every tool call needs exactly one result.")
        return self


class ChatHistoryMessage(BaseModel):
    """An earlier question or answer, for follow-up questions (never a source of facts)."""

    role: Literal["user", "assistant"]
    content: str = Field(min_length=1, max_length=4000)


class ChatRequest(BaseModel):
    # The record whose page the person is on, if any (logs only; the context says the rest).
    record_id: str | None = Field(default=None, pattern=ULID_PATTERN)
    question: str = Field(min_length=1, max_length=2000)
    # Where the person is and their role, written by Laravel. Data, not instructions.
    context: str | None = Field(default=None, max_length=2000)
    history: list[ChatHistoryMessage] = Field(default=[], max_length=12)
    tools: list[ChatTool] = Field(min_length=1, max_length=16)
    steps: list[ChatStep] = Field(default=[], max_length=6)
    # "required" on the first step (look the facts up before answering), "auto" after it,
    # "none" when the tool budget is spent and the model must answer now.
    tool_choice: Literal["required", "auto", "none"] = "auto"

    @model_validator(mode="after")
    def _calls_name_listed_tools(self) -> "ChatRequest":
        listed = {tool.name for tool in self.tools}
        if len(listed) != len(self.tools):
            raise ValueError("Tool names must be unique.")
        for step in self.steps:
            for call in step.calls:
                if call.name not in listed:
                    raise ValueError(f"Unknown tool {call.name!r}.")
        return self


class ChatCitation(BaseModel):
    """A moment of one record the answer relies on, in seconds."""

    record_id: str
    start: float = Field(ge=0)
    end: float = Field(ge=0)


class ChatAnswer(BaseModel):
    """The model's final answer (Structured Outputs)."""

    answer: str
    # True only when the answer is supported by what the tools returned.
    grounded: bool
    citations: list[ChatCitation]


class ChatResponse(BaseModel):
    type: Literal["tool_calls", "answer"]
    tool_calls: list[ChatToolCall] = []
    answer: ChatAnswer | None = None
    model: str
    usage: list[Usage] = []


class EmbedRequest(BaseModel):
    record_id: str | None = Field(default=None, pattern=ULID_PATTERN)
    texts: list[str] = Field(min_length=1, max_length=256)
    dimensions: int = Field(default=1536, ge=64, le=3072)

    @field_validator("texts")
    @classmethod
    def _non_empty(cls, texts: list[str]) -> list[str]:
        cleaned = [" ".join(text.split()) for text in texts]
        if any(not text for text in cleaned):
            raise ValueError("Texts must not be empty.")
        if any(len(text) > 8000 for text in cleaned):
            raise ValueError("Each text must be at most 8000 characters.")
        return cleaned


class EmbedResult(BaseModel):
    model: str
    dimensions: int
    vectors: list[list[float]]
    usage: list[Usage] = []
