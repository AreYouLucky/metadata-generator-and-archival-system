"""OpenAI Responses API with Structured Outputs (`client.responses.parse(text_format=...)`).

Prompt order is fixed for OpenAI's automatic prompt caching: static instructions first,
then the transcript, then the per-request part (preset, custom prompt). A re-run over the
same transcript then bills most input tokens at the cached rate.
"""

import json
from typing import Any

import openai
from openai import OpenAI
from openai.types.responses import (
    EasyInputMessageParam,
    FunctionToolParam,
    ResponseFunctionToolCallParam,
    ResponseInputImageParam,
    ResponseInputMessageContentListParam,
    ResponseInputParam,
    ResponseInputTextParam,
)
from openai.types.responses.response_input_param import FunctionCallOutput
from openai.types.shared_params import Reasoning
from pydantic import ValidationError
from tenacity import retry, retry_if_exception_type, stop_after_attempt, wait_exponential

from app.config import ReasoningEffort, Settings, get_settings
from app.providers.llm.base import (
    ChatTurn,
    FunctionSpec,
    ImageInput,
    LlmError,
    LlmUsage,
    RequestedToolCall,
    T,
    ToolChoice,
    TurnMessage,
    TurnToolCall,
)

_TRANSIENT = (
    openai.APIConnectionError,
    openai.APITimeoutError,
    openai.RateLimitError,
    openai.InternalServerError,
)


class OpenAILlmProvider:
    def __init__(self, settings: Settings | None = None, client: Any = None) -> None:
        self.settings = settings or get_settings()
        self.client = client or OpenAI(
            api_key=self.settings.openai_api_key.get_secret_value(),
            timeout=self.settings.openai_timeout_seconds,
        )

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
        usage = LlmUsage(model=model)
        conversation: ResponseInputParam = [
            EasyInputMessageParam(role="system", content=instructions),
            *(EasyInputMessageParam(role="user", content=message) for message in messages[:-1]),
        ]
        if messages:
            conversation.append(
                EasyInputMessageParam(role="user", content=_content(messages[-1], images or []))
            )

        # Structured Outputs guarantees the schema, but a refusal or a cut-off answer
        # can still fail: retry once, telling the model what went wrong.
        for attempt in range(2):
            response = self._send(model, conversation, schema, reasoning_effort, max_output_tokens)
            _add_usage(usage, response)
            try:
                parsed = _parsed(response, schema)
                return parsed, usage
            except (LlmError, ValidationError) as error:
                if attempt == 1:
                    raise LlmError(f"The model did not return valid metadata: {error}") from error
                conversation.append(
                    EasyInputMessageParam(
                        role="user",
                        content="Your previous answer could not be used "
                        f"({str(error)[:300]}). Answer again with valid JSON only, "
                        "keeping every field short enough to fit.",
                    )
                )
        raise LlmError("unreachable")  # pragma: no cover

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
        """One step of a function-calling loop (Responses API, strict function tools).

        Earlier calls are replayed as `function_call` items without their item ids, so no
        reasoning items are needed and nothing is stored at OpenAI (`store=False`)."""
        usage = LlmUsage(model=model)
        conversation: ResponseInputParam = [
            EasyInputMessageParam(role="system", content=instructions),
            *(_turn(turn) for turn in turns),
        ]
        function_tools = [
            FunctionToolParam(
                type="function",
                name=tool.name,
                description=tool.description,
                parameters=tool.parameters,
                strict=True,
            )
            for tool in tools
        ]

        for attempt in range(2):
            response = self._send_with_tools(
                model,
                conversation,
                function_tools,
                tool_choice,
                schema,
                reasoning_effort,
                max_output_tokens,
            )
            _add_usage(usage, response)
            calls = [
                item
                for item in getattr(response, "output", None) or []
                if getattr(item, "type", None) == "function_call"
            ]
            if calls and tool_choice != "none":
                return [
                    RequestedToolCall(
                        call_id=str(call.call_id),
                        name=str(call.name),
                        arguments=_arguments(getattr(call, "arguments", None)),
                    )
                    for call in calls
                ], usage
            try:
                return _parsed(response, schema), usage
            except (LlmError, ValidationError) as error:
                if attempt == 1:
                    raise LlmError(f"The model did not return a valid answer: {error}") from error
                conversation.append(
                    EasyInputMessageParam(
                        role="user",
                        content="Your previous answer could not be used "
                        f"({str(error)[:300]}). Answer again with valid JSON only.",
                    )
                )
        raise LlmError("unreachable")  # pragma: no cover

    @retry(
        retry=retry_if_exception_type(_TRANSIENT),
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, max=20),
        reraise=True,
    )
    def _send_with_tools(
        self,
        model: str,
        conversation: ResponseInputParam,
        tools: list[FunctionToolParam],
        tool_choice: ToolChoice,
        schema: type[T],
        reasoning_effort: ReasoningEffort,
        max_output_tokens: int,
    ) -> Any:
        return self.client.responses.parse(
            model=model,
            input=conversation,
            tools=tools,
            tool_choice=tool_choice,
            parallel_tool_calls=True,
            text_format=schema,
            reasoning=Reasoning(effort=reasoning_effort),
            max_output_tokens=max_output_tokens,
            store=False,
        )

    @retry(
        retry=retry_if_exception_type(_TRANSIENT),
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=2, max=60),
        reraise=True,
    )
    def _send(
        self,
        model: str,
        conversation: ResponseInputParam,
        schema: type[T],
        reasoning_effort: ReasoningEffort,
        max_output_tokens: int,
    ) -> Any:
        return self.client.responses.parse(
            model=model,
            input=conversation,
            text_format=schema,
            reasoning=Reasoning(effort=reasoning_effort),
            max_output_tokens=max_output_tokens,
        )


def _content(text: str, images: list[ImageInput]) -> str | ResponseInputMessageContentListParam:
    """Plain text, or the text followed by the images (Responses API `input_image`)."""
    if not images:
        return text
    parts: ResponseInputMessageContentListParam = [
        ResponseInputTextParam(type="input_text", text=text)
    ]
    parts.extend(
        ResponseInputImageParam(type="input_image", image_url=image.data_url, detail=image.detail)
        for image in images
    )
    return parts


def _turn(
    turn: ChatTurn,
) -> EasyInputMessageParam | ResponseFunctionToolCallParam | FunctionCallOutput:
    if isinstance(turn, TurnMessage):
        return EasyInputMessageParam(role=turn.role, content=turn.content)
    if isinstance(turn, TurnToolCall):
        return ResponseFunctionToolCallParam(
            type="function_call", call_id=turn.call_id, name=turn.name, arguments=turn.arguments
        )
    return FunctionCallOutput(type="function_call_output", call_id=turn.call_id, output=turn.output)


def _arguments(raw: object) -> dict[str, Any]:
    """The call's JSON arguments; anything unreadable becomes {} (Laravel's validation then
    tells the model what is missing)."""
    if not isinstance(raw, str):
        return {}
    try:
        value = json.loads(raw)
    except json.JSONDecodeError:
        return {}
    return value if isinstance(value, dict) else {}


def _parsed(response: Any, schema: type[T]) -> T:
    if getattr(response, "status", None) == "incomplete":
        details = getattr(response, "incomplete_details", None)
        reason = getattr(details, "reason", None) or "unknown"
        raise LlmError(f"the answer was cut off ({reason})")
    parsed = getattr(response, "output_parsed", None)
    if parsed is None:
        raise LlmError("the model refused or returned no structured output")
    if isinstance(parsed, schema):
        return parsed
    return schema.model_validate(parsed)


def _add_usage(usage: LlmUsage, response: Any) -> None:
    raw = getattr(response, "usage", None)
    if raw is None:
        return
    input_details = getattr(raw, "input_tokens_details", None)
    usage.input_tokens += int(getattr(raw, "input_tokens", 0) or 0)
    usage.cached_tokens += int(getattr(input_details, "cached_tokens", 0) or 0)
    usage.output_tokens += int(getattr(raw, "output_tokens", 0) or 0)
