"""pyannote.audio on this machine (the default diarization provider, optional extra).

`pyannote/speaker-diarization-community-1` (pyannote.audio 4.x) is gated: accept its
terms on Hugging Face and set HF_TOKEN. It runs on the CPU unless DIARIZATION_DEVICE=cuda;
the audio never leaves the machine and costs nothing. The audio is decoded by ffmpeg (as
for local Whisper) and handed over as a waveform, so pyannote's own decoder isn't needed.
The pipeline is loaded once per worker process and kept.
"""

import logging
import os
import threading
from collections.abc import Callable, Iterable
from pathlib import Path
from typing import Any

from app.config import Settings, get_settings
from app.providers.diarization.base import DiarizationError, DiarizationResult
from app.providers.transcription.local import WHISPER_SAMPLE_RATE, decode_audio
from app.schemas.common import SpeakerTurn

logger = logging.getLogger(__name__)

# pyannote sends usage telemetry unless this is off.
os.environ.setdefault("PYANNOTE_METRICS_ENABLED", "0")

_pipelines: dict[tuple[str, str], Any] = {}
_lock = threading.Lock()


def _load(settings: Settings) -> Any:
    key = (settings.diarization_model, settings.diarization_device)
    with _lock:
        if key not in _pipelines:
            token = settings.hf_token.get_secret_value()
            if not token:
                raise DiarizationError(
                    "Set HF_TOKEN in ai-service/.env and accept the terms of "
                    f"{settings.diarization_model} on Hugging Face to identify speakers."
                )
            try:
                import torch
                from pyannote.audio import Pipeline
            except ImportError as error:
                raise DiarizationError(
                    "Speaker identification isn't installed in the AI service image "
                    "(build it with DIARIZATION=true)."
                ) from error
            try:
                pipeline = Pipeline.from_pretrained(settings.diarization_model, token=token)
            except Exception as error:  # noqa: BLE001 - shown as a setup problem
                logger.exception("pyannote pipeline %s could not load", settings.diarization_model)
                raise DiarizationError(
                    f"The speaker model {settings.diarization_model} could not be loaded. "
                    "Check HF_TOKEN and that its terms are accepted on Hugging Face."
                ) from error
            if pipeline is None:
                raise DiarizationError(
                    f"Hugging Face refused {settings.diarization_model}: accept its terms first."
                )
            pipeline.to(torch.device(settings.diarization_device))
            _pipelines[key] = pipeline
        return _pipelines[key]


class _Progress:
    """pyannote calls its hook with (step_name, artefact, file=, total=, completed=)."""

    def __init__(self, on_progress: Callable[[int], None]) -> None:
        self.on_progress = on_progress
        self.steps: list[str] = []

    def __call__(self, step_name: str, *_: Any, **kwargs: Any) -> None:
        if step_name not in self.steps:
            self.steps.append(step_name)
        total, completed = kwargs.get("total"), kwargs.get("completed")
        within = completed / total if total and completed is not None else 0.0
        # Segmentation and embeddings are the slow steps; spread them over 10-95.
        self.on_progress(min(95, 10 + int(40 * (len(self.steps) - 1 + within))))


class PyannoteDiarizer:
    def __init__(self, settings: Settings | None = None, pipeline: Any = None) -> None:
        self.settings = settings or get_settings()
        self._pipeline = pipeline

    def diarize(
        self, audio: Path, duration: float | None, on_progress: Callable[[int], None]
    ) -> DiarizationResult:
        pipeline = self._pipeline or _load(self.settings)
        samples = decode_audio(audio)
        on_progress(5)

        import torch

        waveform = torch.from_numpy(samples.copy()).unsqueeze(0)
        output = pipeline(
            {"waveform": waveform, "sample_rate": WHISPER_SAMPLE_RATE}, hook=_Progress(on_progress)
        )
        return DiarizationResult(
            provider="pyannote",
            model=self.settings.diarization_model,
            turns=turns_of(output),
            usage=[],
        )


def turns_of(output: Any) -> list[SpeakerTurn]:
    """Speaker turns from pyannote's output: 4.x returns an object whose exclusive
    diarization (one speaker at a time, the one to align with transcripts) is preferred;
    3.x returns an Annotation."""
    annotation = (
        getattr(output, "exclusive_speaker_diarization", None)
        or getattr(output, "speaker_diarization", None)
        or output
    )
    pairs: Iterable[tuple[Any, Any]]
    if hasattr(annotation, "itertracks"):
        pairs = ((turn, label) for turn, _, label in annotation.itertracks(yield_label=True))
    else:
        pairs = annotation
    turns = [
        SpeakerTurn(
            start=float(turn.start),
            end=max(float(turn.start), float(turn.end)),
            speaker=str(label)[:32],
        )
        for turn, label in pairs
    ]
    return sorted(turns, key=lambda turn: turn.start)
