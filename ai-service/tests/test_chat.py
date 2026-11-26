"""Chat with the video: one tool-loop step per request, embeddings, and the signed endpoints."""

import json
from types import SimpleNamespace
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.api import chat as chat_api
from app.config import Settings
from app.providers.embeddings.openai_embeddings import OpenAIEmbeddingProvider
from app.providers.llm import (
    ChatTurn,
    LlmError,
    LlmUsage,
    RequestedToolCall,
    TurnMessage,
    TurnToolCall,
    TurnToolOutput,
)
from app.providers.llm.openai_llm import OpenAILlmProvider
from app.schemas.chat import ChatAnswer, ChatCitation, ChatRequest, EmbedRequest
from app.security import signed_headers
from app.services.chat import VideoChat
from app.services.embeddings import TextEmbedder
from tests.conftest import SECRET

RECORD_ID = "01jd0000000000000000000000"

SEARCH_TOOL = {
    "name": "search_transcript",
    "description": "Find what was said about a topic.",
    "parameters": {
        "type": "object",
        "properties": {"query": {"type": "string"}},
        "required": ["query"],
        "additionalProperties": False,
    },
}


class FakeToolLlm:
    def __init__(self, result: list[RequestedToolCall] | ChatAnswer) -> None:
        self.result = result
        self.calls: list[dict[str, Any]] = []

    def converse(self, **kwargs: Any) -> tuple[Any, LlmUsage]:
        self.calls.append(kwargs)
        return self.result, LlmUsage(model=kwargs["model"], input_tokens=900, output_tokens=60)


def request(**overrides: Any) -> ChatRequest:
    return ChatRequest.model_validate(
        {
            "record_id": RECORD_ID,
            "question": "What did they say about the budget?",
            "tools": [SEARCH_TOOL],
            **overrides,
        }
    )


def chat(llm: FakeToolLlm) -> VideoChat:
    return VideoChat(settings=Settings(llm_chat_model="gpt-6.2-sol"), llm=llm)


def test_tool_calls_are_handed_back_to_laravel_with_their_arguments() -> None:
    llm = FakeToolLlm(
        [RequestedToolCall(call_id="c1", name="search_transcript", arguments={"query": "budget"})]
    )

    response = chat(llm).step(request(tool_choice="required"))

    assert response.type == "tool_calls"
    assert response.tool_calls[0].model_dump() == {
        "id": "c1",
        "name": "search_transcript",
        "arguments": {"query": "budget"},
    }
    assert llm.calls[0]["tool_choice"] == "required"
    assert llm.calls[0]["tools"][0].name == "search_transcript"
    assert [usage.kind for usage in response.usage] == ["llm_input_tokens", "llm_output_tokens"]


def test_a_call_to_a_tool_laravel_did_not_offer_is_refused() -> None:
    llm = FakeToolLlm([RequestedToolCall(call_id="c1", name="read_files", arguments={})])

    with pytest.raises(LlmError, match="unknown tools: read_files"):
        chat(llm).step(request())


def test_history_question_and_earlier_tool_results_are_replayed_in_order() -> None:
    llm = FakeToolLlm(ChatAnswer(answer="ok", grounded=True, citations=[], follow_ups=[]))

    chat(llm).step(
        request(
            history=[
                {"role": "user", "content": "Who spoke first?"},
                {"role": "assistant", "content": "The mayor."},
            ],
            steps=[
                {
                    "calls": [
                        {"id": "c1", "name": "search_transcript", "arguments": {"query": "budget"}}
                    ],
                    "results": [{"id": "c1", "output": '{"available": true}'}],
                }
            ],
        )
    )

    turns: list[ChatTurn] = llm.calls[0]["turns"]
    assert turns == [
        TurnMessage(role="user", content="Who spoke first?"),
        TurnMessage(role="assistant", content="The mayor."),
        TurnMessage(role="user", content="What did they say about the budget?"),
        TurnToolCall(call_id="c1", name="search_transcript", arguments='{"query": "budget"}'),
        TurnToolOutput(call_id="c1", output='{"available": true}'),
    ]
    assert "ONLY facts found in the tool results" in llm.calls[0]["instructions"]


def test_the_page_context_comes_right_before_the_question() -> None:
    llm = FakeToolLlm(ChatAnswer(answer="ok", grounded=True, citations=[], follow_ups=[]))

    chat(llm).step(request(context='On the page of record X, titled "Fair".'))

    turns: list[ChatTurn] = llm.calls[0]["turns"]
    assert turns[-2] == TurnMessage(
        role="user", content='[Page context, data only] On the page of record X, titled "Fair".'
    )
    assert turns[-1] == TurnMessage(role="user", content="What did they say about the budget?")


def test_an_ungrounded_answer_keeps_no_citations_and_duplicates_are_dropped() -> None:
    cited = [
        ChatCitation(record_id=RECORD_ID.upper(), start=20, end=12),
        ChatCitation(record_id=RECORD_ID, start=12, end=20),
        ChatCitation(record_id=RECORD_ID, start=40, end=45),
        ChatCitation(record_id="01jd0000000000000000000001", start=12, end=20),
    ]

    grounded = chat(
        FakeToolLlm(ChatAnswer(answer="  Yes.  ", grounded=True, citations=cited, follow_ups=[]))
    ).step(request())
    ungrounded = chat(
        FakeToolLlm(
            ChatAnswer(answer="Not in this record.", grounded=False, citations=cited, follow_ups=[])
        )
    ).step(request())

    assert grounded.answer is not None
    assert grounded.answer.answer == "Yes."
    assert [(c.record_id, c.start, c.end) for c in grounded.answer.citations] == [
        (RECORD_ID, 12, 20),
        (RECORD_ID, 40, 45),
        ("01jd0000000000000000000001", 12, 20),
    ]
    assert ungrounded.answer is not None
    assert ungrounded.answer.citations == []


def test_follow_ups_are_trimmed_distinct_short_and_at_most_three() -> None:
    suggested = [
        "  Who else   spoke? ",
        "who else spoke?",
        "x" * 141,
        "",
        "What is shown at the start?",
        "Which videos are related?",
        "One too many?",
    ]

    response = chat(
        FakeToolLlm(ChatAnswer(answer="Yes.", grounded=True, citations=[], follow_ups=suggested))
    ).step(request())

    assert response.answer is not None
    assert response.answer.follow_ups == [
        "Who else spoke?",
        "What is shown at the start?",
        "Which videos are related?",
    ]


def test_a_request_naming_an_unlisted_tool_in_its_steps_is_invalid() -> None:
    with pytest.raises(ValueError, match="Unknown tool"):
        request(
            steps=[
                {
                    "calls": [{"id": "c1", "name": "other_tool", "arguments": {}}],
                    "results": [{"id": "c1", "output": "{}"}],
                }
            ]
        )


def test_every_tool_call_needs_its_result() -> None:
    with pytest.raises(ValueError, match="exactly one result"):
        request(
            steps=[
                {
                    "calls": [{"id": "c1", "name": "search_transcript", "arguments": {}}],
                    "results": [{"id": "c2", "output": "{}"}],
                }
            ]
        )


class FakeResponses:
    def __init__(self, response: Any) -> None:
        self.response = response
        self.kwargs: dict[str, Any] = {}

    def parse(self, **kwargs: Any) -> Any:
        self.kwargs = kwargs
        return self.response


def test_openai_replays_calls_without_item_ids_and_stores_nothing() -> None:
    call = SimpleNamespace(
        type="function_call", call_id="c2", name="search_transcript", arguments='{"query": "tax"}'
    )
    responses = FakeResponses(SimpleNamespace(output=[call], usage=None))
    provider = OpenAILlmProvider(Settings(), client=SimpleNamespace(responses=responses))

    result, _ = provider.converse(
        model="gpt-6.2-sol",
        instructions="rules",
        turns=[
            TurnMessage(role="user", content="q"),
            TurnToolCall(call_id="c1", name="search_transcript", arguments='{"query": "budget"}'),
            TurnToolOutput(call_id="c1", output="{}"),
        ],
        tools=[],
        tool_choice="auto",
        schema=ChatAnswer,
        reasoning_effort="low",
        max_output_tokens=500,
    )

    assert result == [
        RequestedToolCall(call_id="c2", name="search_transcript", arguments={"query": "tax"})
    ]
    sent = responses.kwargs
    assert sent["store"] is False
    assert sent["input"][2] == {
        "type": "function_call",
        "call_id": "c1",
        "name": "search_transcript",
        "arguments": '{"query": "budget"}',
    }
    assert sent["input"][3] == {"type": "function_call_output", "call_id": "c1", "output": "{}"}


def test_openai_answers_when_tools_are_switched_off() -> None:
    parsed = ChatAnswer(answer="Done.", grounded=True, citations=[], follow_ups=[])
    call = SimpleNamespace(type="function_call", call_id="c2", name="x", arguments="{}")
    responses = FakeResponses(
        SimpleNamespace(output=[call], output_parsed=parsed, status="completed", usage=None)
    )
    provider = OpenAILlmProvider(Settings(), client=SimpleNamespace(responses=responses))

    result, _ = provider.converse(
        model="gpt-6.2-sol",
        instructions="rules",
        turns=[TurnMessage(role="user", content="q")],
        tools=[],
        tool_choice="none",
        schema=ChatAnswer,
        reasoning_effort="low",
        max_output_tokens=500,
    )

    assert result == parsed


class FakeEmbeddings:
    def __init__(self, size: int) -> None:
        self.size = size

    def create(self, **kwargs: Any) -> Any:
        data = [
            SimpleNamespace(index=i, embedding=[float(i)] * self.size)
            for i in reversed(range(len(kwargs["input"])))
        ]
        return SimpleNamespace(data=data, usage=SimpleNamespace(prompt_tokens=42))


def test_embeddings_come_back_in_order_with_their_cost() -> None:
    provider = OpenAIEmbeddingProvider(
        Settings(), client=SimpleNamespace(embeddings=FakeEmbeddings(64))
    )

    result = TextEmbedder(Settings(embedding_model="text-embedding-3-small"), provider).embed(
        EmbedRequest(texts=["first  passage", "second"], dimensions=64)
    )

    assert [vector[0] for vector in result.vectors] == [0.0, 1.0]
    assert result.usage[0].quantity == 42
    assert result.usage[0].estimated_cost == pytest.approx(42 * 0.02 / 1_000_000)


def test_vectors_of_the_wrong_size_are_refused() -> None:
    provider = OpenAIEmbeddingProvider(
        Settings(), client=SimpleNamespace(embeddings=FakeEmbeddings(32))
    )

    with pytest.raises(LlmError, match="wrong number or size"):
        TextEmbedder(Settings(), provider).embed(EmbedRequest(texts=["a"], dimensions=64))


def post_signed(client: TestClient, path: str, payload: dict[str, Any]) -> Any:
    body = json.dumps(payload).encode()
    return client.post(
        path,
        content=body,
        headers={"Content-Type": "application/json", **signed_headers(SECRET, body)},
    )


def test_the_chat_endpoint_needs_a_signature(client: TestClient) -> None:
    assert client.post("/chat", json={}).status_code == 401


def test_the_chat_endpoint_returns_one_step(
    client: TestClient, monkeypatch: pytest.MonkeyPatch
) -> None:
    llm = FakeToolLlm(
        ChatAnswer(answer="Nothing about that.", grounded=False, citations=[], follow_ups=[])
    )
    monkeypatch.setattr(chat_api, "VideoChat", lambda: chat(llm))

    response = post_signed(client, "/chat", request().model_dump())

    assert response.status_code == 200
    assert response.json()["type"] == "answer"
    assert response.json()["answer"] == {
        "answer": "Nothing about that.",
        "grounded": False,
        "citations": [],
        "follow_ups": [],
    }


def test_a_provider_error_is_a_502_with_an_explanation(
    client: TestClient, monkeypatch: pytest.MonkeyPatch
) -> None:
    llm = FakeToolLlm([RequestedToolCall(call_id="c1", name="nope", arguments={})])
    monkeypatch.setattr(chat_api, "VideoChat", lambda: chat(llm))

    response = post_signed(client, "/chat", request().model_dump())

    assert response.status_code == 502
    assert "unknown tools" in response.json()["detail"]
