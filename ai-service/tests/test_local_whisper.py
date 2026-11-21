from types import SimpleNamespace
from typing import Any

import faster_whisper
import pytest

from app.config import Settings
from app.pipeline.tasks import user_message
from app.providers.transcription import local
from app.providers.transcription.local import LocalWhisperProvider, ModelNotReadyError

INFO = SimpleNamespace(duration=10.0, language="tl", language_probability=0.9)
SEGMENT = SimpleNamespace(start=0.0, end=4.0, text=" Magandang umaga po.")


class FakeModel:
    def __init__(self) -> None:
        self.calls: list[dict[str, Any]] = []

    def transcribe(self, audio: str, **options: Any) -> tuple[list[Any], Any]:
        self.calls.append(options)
        return [SEGMENT], INFO


def test_speech_is_decoded_in_batches_with_sentence_level_timestamps(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Any
) -> None:
    batched: list[dict[str, Any]] = []

    class FakeBatchedPipeline:
        def __init__(self, model: Any) -> None:
            self.model = model

        def transcribe(self, audio: str, **options: Any) -> tuple[list[Any], Any]:
            batched.append(options)
            return [SEGMENT], INFO

    monkeypatch.setattr(faster_whisper, "BatchedInferencePipeline", FakeBatchedPipeline)
    model = FakeModel()
    provider = LocalWhisperProvider(Settings(local_whisper_batch_size=8), model=model)

    result = provider.transcribe(tmp_path / "audio.wav", 10.0, lambda _: None)

    assert model.calls == []
    assert batched[0]["batch_size"] == 8
    assert batched[0]["without_timestamps"] is False
    assert [segment.text for segment in result.segments] == ["Magandang umaga po."]


def test_a_batch_size_of_one_decodes_sequentially(tmp_path: Any) -> None:
    model = FakeModel()
    provider = LocalWhisperProvider(Settings(local_whisper_batch_size=1), model=model)

    provider.transcribe(tmp_path / "audio.wav", 10.0, lambda _: None)

    assert len(model.calls) == 1
    assert "batch_size" not in model.calls[0]


def test_a_model_that_is_not_downloaded_fails_fast_with_a_clear_message(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Any
) -> None:
    calls: list[dict[str, Any]] = []

    def not_cached(*args: Any, **kwargs: Any) -> None:
        calls.append(kwargs)
        raise FileNotFoundError("no cached snapshot")

    monkeypatch.setattr(faster_whisper, "WhisperModel", not_cached)
    monkeypatch.setattr(local, "_models", {})
    provider = LocalWhisperProvider(Settings(local_whisper_model="large-v3-turbo"))

    with pytest.raises(ModelNotReadyError) as error:
        provider.transcribe(tmp_path / "audio.wav", 60.0, lambda _: None)

    # Never downloads inside a job.
    assert calls[0]["local_files_only"] is True
    assert user_message("transcribe", error.value) == (
        "The Whisper model 'large-v3-turbo' is still downloading on the AI service. "
        "Retry once it has finished."
    )
