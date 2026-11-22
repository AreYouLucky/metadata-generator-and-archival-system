"""faster-whisper on the local NVIDIA GPU (the project default, see DECISIONS.md).

The audio never leaves the machine and costs no tokens. The model is loaded once per
worker process and kept; the GPU worker runs with concurrency 1 so two copies of
the model never compete for the 6 GB card.

Jobs only ever load the model from the local cache. The GPU worker downloads it in
the background when it starts (see `prefetch_model`), so a slow connection makes a
job fail fast with a clear message instead of hanging inside a multi-GB download.
"""

import logging
import subprocess  # noqa: S404 - fixed argv, no shell
import threading
from collections.abc import Callable
from pathlib import Path
from typing import Any

from app.config import Settings, get_settings
from app.pipeline.audio import EXTRACT_TIMEOUT_SECONDS, AudioError
from app.pricing import transcription_cost
from app.providers.transcription.base import TranscriptionResult, clean_segments
from app.schemas.common import TranscriptSegment, Usage

logger = logging.getLogger(__name__)

_models: dict[tuple[str, str, str], Any] = {}
_lock = threading.Lock()


class ModelNotReadyError(Exception):
    """The configured Whisper model isn't in the local cache yet."""


def prefetch_model(settings: Settings | None = None) -> None:
    """Download the configured model into HF_HOME (a no-op once it is cached)."""
    from faster_whisper import download_model

    name = (settings or get_settings()).local_whisper_model
    logger.info("Whisper model %s: making sure it is downloaded", name)
    try:
        download_model(name)
    except Exception:  # noqa: BLE001 - logged; the next worker start tries again
        logger.exception("Whisper model %s could not be downloaded", name)
        return
    logger.info("Whisper model %s is ready", name)


def _load(settings: Settings) -> Any:
    key = (
        settings.local_whisper_model,
        settings.local_whisper_device,
        settings.local_whisper_compute_type,
    )
    with _lock:
        if key not in _models:
            from faster_whisper import WhisperModel

            try:
                _models[key] = WhisperModel(
                    settings.local_whisper_model,
                    device=settings.local_whisper_device,
                    compute_type=settings.local_whisper_compute_type,
                    local_files_only=True,
                )
            except FileNotFoundError as error:
                raise ModelNotReadyError(
                    f"The Whisper model '{settings.local_whisper_model}' is still downloading "
                    "on the AI service. Retry once it has finished."
                ) from error
        return _models[key]


WHISPER_SAMPLE_RATE = 16000


def decode_audio(audio: Path) -> Any:
    """The audio as 16 kHz mono float32 samples, decoded by the image's ffmpeg.

    faster-whisper's own decoder goes through PyAV, whose newer releases dropped an
    argument faster-whisper still passes; handing it samples skips that path entirely.
    """
    import numpy as np

    completed = subprocess.run(  # noqa: S603 - fixed argv, path confined to storage
        [  # noqa: S607 - ffmpeg from the image PATH
            "ffmpeg",
            "-nostdin",
            "-loglevel",
            "error",
            "-i",
            str(audio),
            "-ac",
            "1",
            "-ar",
            str(WHISPER_SAMPLE_RATE),
            "-f",
            "f32le",
            "pipe:1",
        ],
        capture_output=True,
        timeout=EXTRACT_TIMEOUT_SECONDS,
        check=False,
    )
    if completed.returncode != 0:
        message = completed.stderr.decode(errors="replace").strip()[:500]
        raise AudioError(f"ffmpeg could not read the audio: {message or 'unknown error'}")
    return np.frombuffer(completed.stdout, dtype=np.float32)


class LocalWhisperProvider:
    def __init__(self, settings: Settings | None = None, model: Any = None) -> None:
        self.settings = settings or get_settings()
        self._model = model

    def transcribe(
        self,
        audio: Path,
        duration: float | None,
        on_progress: Callable[[int], None],
    ) -> TranscriptionResult:
        model = self._model or _load(self.settings)
        samples = decode_audio(audio)
        options: dict[str, Any] = {
            "beam_size": 5,
            # Skips long silences: faster, and fewer hallucinated lines in quiet parts.
            "vad_filter": True,
            "word_timestamps": False,
        }
        batch_size = self.settings.local_whisper_batch_size

        if batch_size > 1:
            from faster_whisper import BatchedInferencePipeline

            # Decodes several speech chunks at once: several times faster on long videos.
            # Timestamps stay on, or each segment would be a whole ~30 s chunk and the
            # transcript editor and timeline would lose their sentence-level lines.
            segments_iter, info = BatchedInferencePipeline(model=model).transcribe(
                samples, batch_size=batch_size, without_timestamps=False, **options
            )
        else:
            segments_iter, info = model.transcribe(samples, **options)
        total = float(getattr(info, "duration", None) or duration or 0.0)

        segments: list[TranscriptSegment] = []
        for segment in segments_iter:  # transcription runs while iterating
            segments.append(
                TranscriptSegment(start=segment.start, end=segment.end, text=segment.text)
            )
            if total:
                on_progress(min(99, int(segment.end * 100 / total)))

        minutes = round(total / 60, 4)
        model_name = f"faster-whisper/{self.settings.local_whisper_model}"
        return TranscriptionResult(
            provider="local",
            model=model_name,
            language=getattr(info, "language", None),
            language_confidence=_probability(getattr(info, "language_probability", None)),
            duration=total,
            segments=clean_segments(segments),
            usage=[
                Usage(
                    kind="transcription_minutes",
                    quantity=minutes,
                    estimated_cost=transcription_cost("local", minutes),
                    model=model_name,
                )
            ],
        )


def _probability(value: Any) -> float | None:
    try:
        return round(min(max(float(value), 0.0), 1.0), 4)
    except (TypeError, ValueError):
        return None
