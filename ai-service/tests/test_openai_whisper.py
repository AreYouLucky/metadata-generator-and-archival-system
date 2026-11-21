"""The OpenAI transcription provider: chunking over the upload limit, offsets, overlap."""

from pathlib import Path
from typing import Any

import pytest

from app.config import Settings
from app.providers.transcription import openai_whisper
from app.providers.transcription.openai_whisper import OpenAIWhisperProvider, language_code


class FakeTranscriptions:
    def __init__(self) -> None:
        self.calls = 0

    def create(self, **kwargs: Any) -> dict[str, Any]:
        self.calls += 1
        # Each chunk: one line at 0-5 s and one in the 2 s overlap at its end (600-602 s).
        return {
            "language": "tagalog",
            "segments": [
                {"start": 0.0, "end": 5.0, "text": f" Chunk {self.calls} start"},
                {"start": 600.5, "end": 601.5, "text": f" Chunk {self.calls} overlap"},
            ],
        }


class FakeClient:
    def __init__(self) -> None:
        self.audio = type("Audio", (), {"transcriptions": FakeTranscriptions()})()


def test_audio_over_the_upload_limit_is_sent_in_chunks_with_offsets_and_no_duplicates(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    audio = tmp_path / "audio.mp3"
    audio.write_bytes(b"x" * 100)
    monkeypatch.setattr(
        openai_whisper,
        "cut",
        lambda source, target, start, length: target.write_bytes(b"x") or target,
    )
    client = FakeClient()
    provider = OpenAIWhisperProvider(
        Settings(openai_max_upload_bytes=10, openai_chunk_seconds=600),
        client=client,  # type: ignore[arg-type]
    )

    result = provider.transcribe(audio, 1500.0, lambda progress: None)

    assert client.audio.transcriptions.calls == 3
    # Each later chunk's first line repeats the previous chunk's overlap and is dropped.
    assert [s.start for s in result.segments] == [0.0, 600.5, 1200.5, 1800.5]
    assert result.language == "tl"
    assert result.usage[0].quantity == 25.0
    assert result.usage[0].estimated_cost == pytest.approx(0.15)


@pytest.mark.parametrize(
    ("value", "code"), [("english", "en"), ("Tagalog", "tl"), ("en", "en"), ("klingon", None)]
)
def test_language_names_become_iso_codes(value: str, code: str | None) -> None:
    assert language_code(value) == code
