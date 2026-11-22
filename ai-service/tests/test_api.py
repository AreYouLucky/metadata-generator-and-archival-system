import json
from types import SimpleNamespace

import httpx
import pytest
from fastapi.testclient import TestClient

from app import __version__
from app.api import ping as ping_module
from app.security import signed_headers
from tests.conftest import SECRET

PING_ID = "0b5a5a3e-7c1e-4d3a-9f0e-2a6f4b8c9d10"


@pytest.fixture
def queued(monkeypatch: pytest.MonkeyPatch) -> list[str]:
    calls: list[str] = []

    def fake_delay(ping_id: str) -> SimpleNamespace:
        calls.append(ping_id)
        return SimpleNamespace(id="celery-123")

    monkeypatch.setattr(ping_module.send_ping_callback, "delay", fake_delay)
    return calls


def post_signed(client: TestClient, path: str, payload: dict[str, str]) -> httpx.Response:
    body = json.dumps(payload).encode()
    headers = {"Content-Type": "application/json", **signed_headers(SECRET, body)}
    return client.post(path, content=body, headers=headers)


def test_health_is_open_and_reports_the_version(client: TestClient) -> None:
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["status"] == "ok"
    assert response.json()["version"] == __version__
    assert isinstance(response.json()["gpu"], bool)
    assert "gpu_name" in response.json()


def test_health_says_which_providers_hear_the_audio(
    client: TestClient, monkeypatch: pytest.MonkeyPatch
) -> None:
    monkeypatch.setenv("TRANSCRIPTION_PROVIDER", "assemblyai")
    monkeypatch.setenv("DIARIZATION_PROVIDER", "pyannote")

    body = client.get("/health").json()

    assert body["transcription_provider"] == "assemblyai"
    assert body["diarization_provider"] == "pyannote"


def test_a_signed_ping_queues_the_callback_task(client: TestClient, queued: list[str]) -> None:
    response = post_signed(client, "/ping", {"ping_id": PING_ID})

    assert response.status_code == 202
    assert response.json() == {"job_id": "celery-123", "ping_id": PING_ID}
    assert queued == [PING_ID]


def test_an_unsigned_ping_is_rejected(client: TestClient, queued: list[str]) -> None:
    response = client.post("/ping", json={"ping_id": PING_ID})

    assert response.status_code == 401
    assert queued == []


def test_a_replayed_ping_is_rejected(client: TestClient, queued: list[str]) -> None:
    body = json.dumps({"ping_id": PING_ID}).encode()
    headers = {"Content-Type": "application/json", **signed_headers(SECRET, body)}

    assert client.post("/ping", content=body, headers=headers).status_code == 202
    assert client.post("/ping", content=body, headers=headers).status_code == 401
    assert queued == [PING_ID]


def test_a_signed_ping_still_has_its_body_validated(client: TestClient, queued: list[str]) -> None:
    response = post_signed(client, "/ping", {"ping_id": "not-a-uuid"})

    assert response.status_code == 422
    assert queued == []
