"""The diarize stage and pyannote's output, with the pipeline and audio decoding mocked."""

from pathlib import Path
from types import SimpleNamespace
from typing import Any

import pytest

from app.pipeline import stages
from app.pipeline.stages import SkipStage
from app.providers.diarization import DiarizationResult, pyannote
from app.schemas.common import PresetRules, SpeakerTurn, TranscriptSegment
from app.schemas.pipeline import JobOptions, PipelineContext, ProcessRequest

RECORD_ID = "01JDZ8Q7X3V5K9M2N4P6R8T0W1"


def context(**changes: Any) -> PipelineContext:
    request = ProcessRequest(
        record_id=RECORD_ID,
        record_file_id=RECORD_ID,
        video_path="records/x/master/y.mp4",
        options=JobOptions(
            preset=PresetRules(name="generic", label="Generic", description_max=2000),
            diarization=True,
        ),
    )
    return PipelineContext(request=request, **changes)


def segment(start: float) -> TranscriptSegment:
    return TranscriptSegment(start=start, end=start + 1, text="words")


def test_speakers_from_the_transcription_provider_are_reused_without_another_call(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr(stages, "get_diarization_provider", lambda: pytest.fail("called"))
    ctx = context(
        segments=[segment(0)],
        speaker_turns=[SpeakerTurn(start=0, end=1, speaker="A")],
    )

    result = stages.run_diarize(ctx, lambda _: None)

    assert result["turns"] == [{"start": 0.0, "end": 1.0, "speaker": "A"}]
    assert result["usage"] == []


def test_the_configured_provider_labels_the_extracted_audio(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    seen: list[Path] = []

    class Provider:
        def diarize(self, audio: Path, duration: float | None, on_progress: Any) -> Any:
            seen.append(audio)
            return DiarizationResult(
                provider="pyannote",
                model="m",
                turns=[SpeakerTurn(start=0, end=4, speaker="SPEAKER_00")],
            )

    monkeypatch.setattr(stages, "get_diarization_provider", Provider)

    result = stages.run_diarize(
        context(segments=[segment(0)], audio_path="records/x/work/audio.mp3"), lambda _: None
    )

    assert seen == [tmp_path / "records/x/work/audio.mp3"]
    assert result["provider"] == "pyannote"
    assert result["turns"][0]["speaker"] == "SPEAKER_00"


def test_a_video_without_speech_is_skipped() -> None:
    with pytest.raises(SkipStage):
        stages.run_diarize(context(), lambda _: None)


def test_diarize_is_skipped_when_speakers_were_not_asked_for() -> None:
    ctx = context()
    ctx.request.options.diarization = False

    assert ctx.disabled_reason("diarize") == "Speaker identification was turned off for this video."


def test_pyannotes_exclusive_diarization_is_preferred_and_sorted() -> None:
    turn = lambda start, end: SimpleNamespace(start=start, end=end)  # noqa: E731
    output = SimpleNamespace(
        exclusive_speaker_diarization=[
            (turn(5.0, 6.0), "SPEAKER_01"),
            (turn(0.5, 4.0), "SPEAKER_00"),
        ],
        speaker_diarization=[(turn(0, 99), "ignored")],
    )

    assert pyannote.turns_of(output) == [
        SpeakerTurn(start=0.5, end=4.0, speaker="SPEAKER_00"),
        SpeakerTurn(start=5.0, end=6.0, speaker="SPEAKER_01"),
    ]


def test_pyannote_3_annotations_are_read_through_itertracks() -> None:
    class Annotation:
        def itertracks(self, yield_label: bool = False) -> Any:
            yield SimpleNamespace(start=1.0, end=2.0), "A", "SPEAKER_00"

    assert pyannote.turns_of(Annotation()) == [
        SpeakerTurn(start=1.0, end=2.0, speaker="SPEAKER_00")
    ]


def test_pyannote_without_a_token_explains_what_to_set() -> None:
    from app.config import Settings
    from app.providers.diarization.base import DiarizationError

    with pytest.raises(DiarizationError, match="HF_TOKEN"):
        pyannote._load(Settings(hf_token=""))
