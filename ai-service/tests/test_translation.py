"""Transcript translation: batching, line ids, missing lines, callbacks and the signed endpoint.

The LLM is a fake, so the tests show exactly what would be sent to OpenAI."""

import json
from dataclasses import dataclass, field
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.api import jobs
from app.config import Settings
from app.pipeline import translation as task_module
from app.providers.llm import LlmUsage
from app.schemas.translation import LlmLine, LlmTranslation, SourceLine
from app.security import signed_headers
from app.services.translation import Translator, batch_lines
from tests.conftest import SECRET

RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"


@dataclass
class FakeTranslator:
    """Upper-cases each numbered line; can skip some indexes on the first answer."""

    skip_once: set[int] = field(default_factory=set)
    calls: list[list[str]] = field(default_factory=list)

    def complete(self, *, model: str, messages: list[str], **_: Any) -> tuple[Any, LlmUsage]:
        self.calls.append(messages)
        lines = []
        for raw in messages[-1].splitlines():
            index, text = raw.split("|", 1)
            if int(index) in self.skip_once:
                continue
            lines.append(LlmLine(index=int(index), text=text.upper()))
        self.skip_once = set()
        return LlmTranslation(lines=lines), LlmUsage(model=model, input_tokens=50, output_tokens=40)


def lines(count: int) -> list[SourceLine]:
    return [SourceLine(id=100 + i, text=f"linya {i}") for i in range(count)]


def translator(llm: FakeTranslator, **overrides: Any) -> Translator:
    settings = Settings(llm_translation_model="gpt-6.2-sol", **overrides)
    return Translator(settings=settings, llm=llm)


def test_lines_are_batched_by_count_and_size() -> None:
    source = [SourceLine(id=i, text="x" * 40) for i in range(1, 11)]

    assert [len(batch) for batch in batch_lines(source, max_lines=4, max_chars=1000)] == [4, 4, 2]
    assert [len(batch) for batch in batch_lines(source, max_lines=50, max_chars=100)] == [2] * 5


def test_each_line_keeps_its_id_and_only_text_is_sent() -> None:
    llm = FakeTranslator()
    progress: list[int] = []

    result = translator(llm, translation_batch_lines=2).translate(
        lines(3), "Filipino", "en", on_progress=progress.append
    )

    assert [(line.id, line.text) for line in result.lines] == [
        (100, "LINYA 0"),
        (101, "LINYA 1"),
        (102, "LINYA 2"),
    ]
    assert llm.calls[0] == [
        "Source language: en.\nTarget language: Filipino.",
        "1|linya 0\n2|linya 1",
    ]
    assert llm.calls[1][-1] == "1|linya 2"  # indexes restart per batch
    assert progress == [50, 100]
    assert result.kept == 0
    assert sum(u.quantity for u in result.usage if u.kind == "llm_input_tokens") == 100


def test_skipped_lines_are_asked_for_once_more() -> None:
    llm = FakeTranslator(skip_once={2})

    result = translator(llm).translate(lines(3), "Filipino")

    assert llm.calls[1][-1] == "1|linya 1"
    assert [line.text for line in result.lines] == ["LINYA 0", "LINYA 1", "LINYA 2"]


def test_a_line_still_missing_keeps_its_source_text() -> None:
    class AlwaysSkips(FakeTranslator):
        def complete(self, *, model: str, messages: list[str], **kwargs: Any) -> Any:
            self.skip_once = {1}
            return super().complete(model=model, messages=messages, **kwargs)

    result = translator(AlwaysSkips()).translate(lines(2), "Filipino")

    assert [line.text for line in result.lines] == ["linya 0", "LINYA 1"]
    assert result.kept == 1


def request_body(**overrides: Any) -> dict[str, Any]:
    return {
        "record_id": RECORD_ID,
        "transcript_id": 7,
        "source_language": "en",
        "target_language": "fil",
        "target_language_name": "Filipino",
        "lines": [{"id": 100, "text": "linya 0"}],
        **overrides,
    }


def test_the_task_reports_running_then_the_translated_lines(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    posted: list[tuple[str, dict[str, Any]]] = []
    monkeypatch.setattr(task_module, "post_with_retry", lambda path, p: posted.append((path, p)))
    monkeypatch.setattr(task_module, "post_to_laravel", lambda path, p: posted.append((path, p)))
    monkeypatch.setattr(task_module, "Translator", lambda: translator(FakeTranslator()))

    task_module.run_translation(request_body())

    base = f"/internal/records/{RECORD_ID}/translations/7"
    assert posted[0] == (f"{base}/progress", {"status": "running", "progress": 0})
    path, result = posted[-1]
    assert path == f"{base}/result"
    assert result["lines"] == [{"id": 100, "text": "LINYA 0"}]
    assert result["kept"] == 0
    assert result["model"] == "gpt-6.2-sol"


def test_a_failed_translation_is_reported_with_a_readable_message(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    posted: list[tuple[str, dict[str, Any]]] = []

    class Broken:
        def translate(self, *_: Any, **__: Any) -> Any:
            raise RuntimeError("boom")

    monkeypatch.setattr(task_module, "post_with_retry", lambda path, p: posted.append((path, p)))
    monkeypatch.setattr(task_module, "Translator", Broken)

    task_module.run_translation(request_body())

    path, payload = posted[-1]
    assert path.endswith("/progress")
    assert payload["status"] == "failed"
    assert payload["message"] == "The translation step failed unexpectedly (RuntimeError)."


def post_translate(client: TestClient, body: dict[str, Any]) -> Any:
    raw = json.dumps(body).encode()
    return client.post(
        "/jobs/translate",
        content=raw,
        headers={"Content-Type": "application/json", **signed_headers(SECRET, raw)},
    )


def test_a_signed_translate_request_is_queued(
    client: TestClient, monkeypatch: pytest.MonkeyPatch
) -> None:
    queued: list[str] = []
    monkeypatch.setattr(
        jobs, "start_translation", lambda request: queued.append(request.target_language) or "j-1"
    )

    response = post_translate(client, request_body())

    assert response.status_code == 202
    assert response.json() == {"job_id": "j-1"}
    assert queued == ["fil"]


def test_a_translate_request_with_a_bad_language_code_is_refused(client: TestClient) -> None:
    assert post_translate(client, request_body(target_language="Filipino!")).status_code == 422
