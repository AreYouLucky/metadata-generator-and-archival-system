"""AssemblyAI over a mocked HTTP transport: upload, create, poll, sentences."""

import json
from pathlib import Path

import httpx
import pytest

from app.config import Settings
from app.providers.assemblyai import AssemblyAIClient, AssemblyAIError
from app.providers.diarization.assemblyai import AssemblyAIDiarizer
from app.providers.transcription.assemblyai import AssemblyAIProvider


def settings() -> Settings:
    return Settings(assemblyai_api_key="aai-key", assemblyai_poll_seconds=1)


def transport(
    requests: list[httpx.Request], final_status: str = "completed"
) -> httpx.MockTransport:
    polls = iter(["queued", "processing", final_status])

    def handle(request: httpx.Request) -> httpx.Response:
        requests.append(request)
        path = request.url.path
        if path == "/v2/upload":
            return httpx.Response(200, json={"upload_url": "https://cdn.assemblyai.test/a"})
        if path == "/v2/transcript":
            return httpx.Response(200, json={"id": "t1", "status": "queued"})
        if path == "/v2/transcript/t1":
            status = next(polls)
            return httpx.Response(
                200,
                json={
                    "id": "t1",
                    "status": status,
                    "error": "Audio has no speech" if status == "error" else None,
                    "language_code": "en_us",
                    "audio_duration": 120,
                    "speech_model_used": "assemblyai-stt-3-7",
                },
            )
        if path == "/v2/transcript/t1/sentences":
            return httpx.Response(
                200,
                json={
                    "sentences": [
                        {"text": "Good morning.", "start": 1000, "end": 2500, "speaker": "A"},
                        {"text": "Hello.", "start": 2600, "end": 3100, "speaker": "B"},
                    ]
                },
            )
        return httpx.Response(404)

    return httpx.MockTransport(handle)


@pytest.fixture
def audio(tmp_path: Path) -> Path:
    path = tmp_path / "audio.mp3"
    path.write_bytes(b"\x00" * 10)
    return path


def test_transcription_sends_the_audio_and_returns_sentences_speakers_and_cost(
    audio: Path,
) -> None:
    requests: list[httpx.Request] = []
    client = AssemblyAIClient(settings(), transport=transport(requests), sleep=lambda _: None)

    result = AssemblyAIProvider(settings(), client).transcribe(
        audio, 120.0, lambda _: None, speaker_labels=True
    )

    assert requests[0].headers["authorization"] == "aai-key"
    created = json.loads(requests[1].content)
    assert created["speaker_labels"] is True
    assert created["language_detection"] is True
    assert created["speech_models"] == ["assemblyai-stt-3-7", "universal-2"]
    assert result.language == "en"
    assert [(s.start, s.end, s.text) for s in result.segments] == [
        (1.0, 2.5, "Good morning."),
        (2.6, 3.1, "Hello."),
    ]
    assert [t.speaker for t in result.speaker_turns] == ["A", "B"]
    # Turns stay in the pipeline; Laravel gets the transcript only.
    assert "speaker_turns" not in result.model_dump()
    assert result.usage[0].quantity == 2.0
    assert result.usage[0].estimated_cost == pytest.approx(2 * (0.21 + 0.02) / 60)


def test_a_failed_transcript_is_reported_with_assemblyais_reason(audio: Path) -> None:
    client = AssemblyAIClient(settings(), transport=transport([], "error"), sleep=lambda _: None)

    with pytest.raises(AssemblyAIError, match="Audio has no speech"):
        client.transcribe(audio, speaker_labels=False, duration=120.0, on_progress=lambda _: None)


def test_a_missing_key_is_explained_before_anything_is_sent() -> None:
    with pytest.raises(AssemblyAIError, match="ASSEMBLYAI_API_KEY"):
        AssemblyAIClient(Settings(assemblyai_api_key=""))


def test_diarization_alone_keeps_only_the_speaker_turns(audio: Path) -> None:
    client = AssemblyAIClient(settings(), transport=transport([]), sleep=lambda _: None)

    result = AssemblyAIDiarizer(settings(), client).diarize(audio, 120.0, lambda _: None)

    assert [(t.start, t.speaker) for t in result.turns] == [(1.0, "A"), (2.6, "B")]
    assert result.usage[0].kind == "transcription_minutes"
