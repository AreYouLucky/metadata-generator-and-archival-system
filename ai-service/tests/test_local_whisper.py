import subprocess
from types import SimpleNamespace
from typing import Any

import faster_whisper
import pytest

from app.config import Settings
from app.pipeline.audio import AudioError
from app.pipeline.tasks import user_message
from app.providers.transcription import local
from app.providers.transcription.local import LocalWhisperProvider, ModelNotReadyError

INFO = SimpleNamespace(duration=10.0, language="tl", language_probability=0.9)
SEGMENT = SimpleNamespace(start=0.0, end=4.0, text=" Magandang umaga po.")
SAMPLES = object()
DECODE = local.decode_audio  # the real decoder, before the autouse stub replaces it


@pytest.fixture(autouse=True)
def decoded_audio(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(local, "decode_audio", lambda _audio: SAMPLES)


class FakeModel:
    def __init__(self) -> None:
        self.calls: list[dict[str, Any]] = []

    def transcribe(self, audio: Any, **options: Any) -> tuple[list[Any], Any]:
        self.calls.append({"audio": audio, **options})
        return [SEGMENT], INFO


def test_speech_is_decoded_in_batches_with_sentence_level_timestamps(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Any
) -> None:
    batched: list[dict[str, Any]] = []

    class FakeBatchedPipeline:
        def __init__(self, model: Any) -> None:
            self.model = model

        def transcribe(self, audio: Any, **options: Any) -> tuple[list[Any], Any]:
            batched.append({"audio": audio, **options})
            return [SEGMENT], INFO

    monkeypatch.setattr(faster_whisper, "BatchedInferencePipeline", FakeBatchedPipeline)
    model = FakeModel()
    provider = LocalWhisperProvider(Settings(local_whisper_batch_size=8), model=model)

    result = provider.transcribe(tmp_path / "audio.wav", 10.0, lambda _: None)

    assert model.calls == []
    assert batched[0]["audio"] is SAMPLES
    assert batched[0]["batch_size"] == 8
    assert batched[0]["without_timestamps"] is False
    assert [segment.text for segment in result.segments] == ["Magandang umaga po."]


def test_a_batch_size_of_one_decodes_sequentially(tmp_path: Any) -> None:
    model = FakeModel()
    provider = LocalWhisperProvider(Settings(local_whisper_batch_size=1), model=model)

    provider.transcribe(tmp_path / "audio.wav", 10.0, lambda _: None)

    assert len(model.calls) == 1
    assert model.calls[0]["audio"] is SAMPLES
    assert "batch_size" not in model.calls[0]


def test_audio_is_decoded_to_16_khz_mono_samples_without_pyav(tmp_path: Any) -> None:
    tone = tmp_path / "tone.mp3"
    subprocess.run(  # noqa: S603 - fixed argv
        [  # noqa: S607 - ffmpeg from the image PATH
            "ffmpeg",
            "-nostdin",
            "-loglevel",
            "error",
            "-f",
            "lavfi",
            "-i",
            "sine=frequency=440:duration=1",
            "-ac",
            "2",
            "-ar",
            "44100",
            str(tone),
        ],
        check=True,
    )

    samples = DECODE(tone)

    assert samples.dtype == "float32"
    assert abs(len(samples) - 16000) < 1600  # one second, give or take MP3 padding
    assert 0.1 < float(abs(samples).max()) <= 1.0


def test_unreadable_audio_fails_with_an_audio_error(tmp_path: Any) -> None:
    broken = tmp_path / "broken.mp3"
    broken.write_bytes(b"not audio")

    with pytest.raises(AudioError):
        DECODE(broken)


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
