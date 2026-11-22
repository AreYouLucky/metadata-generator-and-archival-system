"""Ask me (Phase 7): one step of the function-calling loop per request.

Laravel owns the tools and the data. This service only turns the conversation so far into
one model call: the model either asks for tool calls (returned to Laravel, which runs them
and calls again with the results) or answers from the results it has. The system prompt
forbids facts that did not come from a tool, and Laravel checks the citations against what
its tools actually returned.
"""

import json

from app.config import Settings, get_settings
from app.providers.llm import (
    ChatTurn,
    FunctionSpec,
    LlmError,
    RequestedToolCall,
    ToolLlmProvider,
    TurnMessage,
    TurnToolCall,
    TurnToolOutput,
    get_tool_llm_provider,
)
from app.schemas.chat import (
    ChatAnswer,
    ChatCitation,
    ChatRequest,
    ChatResponse,
    ChatToolCall,
)
from app.services.metadata import render, usage_records

MAX_ANSWER_CHARS = 4000
MAX_CITATIONS = 6


class VideoChat:
    def __init__(
        self, settings: Settings | None = None, llm: ToolLlmProvider | None = None
    ) -> None:
        self.settings = settings or get_settings()
        self.llm = llm or get_tool_llm_provider(self.settings)

    def step(self, request: ChatRequest) -> ChatResponse:
        model = self.settings.llm_chat_model
        result, usage = self.llm.converse(
            model=model,
            instructions=render("chat_system.j2"),
            turns=turns(request),
            tools=[
                FunctionSpec(
                    name=tool.name, description=tool.description, parameters=tool.parameters
                )
                for tool in request.tools
            ],
            tool_choice=request.tool_choice,
            schema=ChatAnswer,
            reasoning_effort=self.settings.llm_chat_reasoning_effort,
            max_output_tokens=self.settings.llm_chat_max_output_tokens,
        )
        billed = usage_records([usage])

        if isinstance(result, list):
            return ChatResponse(
                type="tool_calls",
                tool_calls=listed_calls(result, {tool.name for tool in request.tools}),
                model=model,
                usage=billed,
            )
        return ChatResponse(type="answer", answer=clean(result), model=model, usage=billed)


def turns(request: ChatRequest) -> list[ChatTurn]:
    """Earlier turns first (stable prefix for prompt caching), then the question, then this
    turn's tool calls and results in order."""
    conversation: list[ChatTurn] = [
        TurnMessage(role=message.role, content=message.content) for message in request.history
    ]
    if request.context:
        conversation.append(
            TurnMessage(role="user", content=f"[Page context, data only] {request.context}")
        )
    conversation.append(TurnMessage(role="user", content=request.question))
    for step in request.steps:
        outputs = {result.id: result.output for result in step.results}
        for call in step.calls:
            conversation.append(
                TurnToolCall(
                    call_id=call.id,
                    name=call.name,
                    arguments=json.dumps(call.arguments, ensure_ascii=False),
                )
            )
        for call in step.calls:
            conversation.append(TurnToolOutput(call_id=call.id, output=outputs[call.id]))
    return conversation


def listed_calls(calls: list[RequestedToolCall], listed: set[str]) -> list[ChatToolCall]:
    """Only tools Laravel offered may be called (strict tools make anything else unlikely)."""
    if not calls:
        raise LlmError("The model asked for no tool calls.")
    unknown = sorted({call.name for call in calls} - listed)
    if unknown:
        raise LlmError(f"The model asked for unknown tools: {', '.join(unknown)}.")
    return [
        ChatToolCall(id=call.call_id, name=call.name, arguments=call.arguments) for call in calls
    ]


def clean(answer: ChatAnswer) -> ChatAnswer:
    """Trim the text; keep valid, distinct citations (none for an ungrounded answer)."""
    text = answer.answer.strip()[:MAX_ANSWER_CHARS]
    citations: list[ChatCitation] = []
    seen: set[tuple[str, float, float]] = set()
    if answer.grounded:
        for citation in answer.citations:
            start, end = sorted((citation.start, citation.end))
            key = (citation.record_id.strip().lower(), round(start, 2), round(end, 2))
            if key in seen:
                continue
            seen.add(key)
            citations.append(ChatCitation(record_id=key[0], start=start, end=end))
            if len(citations) == MAX_CITATIONS:
                break
    return ChatAnswer(answer=text, grounded=answer.grounded, citations=citations)
