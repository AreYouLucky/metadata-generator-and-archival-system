"""OpenAI `whisper-1` (verbose_json, segment timestamps), kept behind the same interface.

whisper-1 is deprecated (shutdown 2027-02-26, see DECISIONS.md) and is the only OpenAI
model documented to return segment timestamps. Audio over the 25 MB upload limit is cut
into ~10-minute chunks with ~2 s overlap; timestamps are offset and the overlap de-duplicated.
"""

import math
from collections.abc import Callable
from pathlib import Path
from typing import Any

from openai import OpenAI
from tenacity import retry, stop_after_attempt, wait_exponential

from app.config import Settings, get_settings
from app.pipeline.audio import cut
from app.pricing import transcription_cost
from app.providers.transcription.base import TranscriptionResult, clean_segments
from app.schemas.common import TranscriptSegment, Usage

# whisper-1 reports the language by name ("english"); store ISO 639-1 like faster-whisper.
_LANGUAGE_CODES = {
    "english": "en",
    "tagalog": "tl",
    "filipino": "tl",
    "spanish": "es",
    "chinese": "zh",
    "japanese": "ja",
    "korean": "ko",
    "indonesian": "id",
    "malay": "ms",
    "vietnamese": "vi",
    "thai": "th",
    "french": "fr",
    "german": "de",
    "arabic": "ar",
    "hindi": "hi",
    "portuguese": "pt",
    "italian": "it",
    "russian": "ru",
}


def language_code(value: str | None) -> str | None:
    if not value:
        return None
    value = value.strip().lower()
    if len(value) in (2, 3) and value.isalpha():
        return value
    return _LANGUAGE_CODES.get(value)


class OpenAIWhisperProvider:
    def __init__(self, settings: Settings | None = None, client: OpenAI | None = None) -> None:
        self.settings = settings or get_settings()
        self.client = client or OpenAI(
            api_key=self.settings.openai_api_key.get_secret_value(),
            timeout=self.settings.openai_timeout_seconds,
        )

    def transcribe(
        self,
        audio: Path,
        duration: float | None,
        on_progress: Callable[[int], None],
    ) -> TranscriptionResult:
        total = float(duration or 0.0)
        model = self.settings.openai_transcription_model

        if audio.stat().st_size <= self.settings.openai_max_upload_bytes or not total:
            response = self._request(audio)
            segments = _segments(response, offset=0.0)
            language = language_code(_field(response, "language"))
            total = total or float(_field(response, "duration") or 0.0)
        else:
            segments, language = self._transcribe_in_chunks(audio, total, on_progress)

        minutes = round(total / 60, 4)
        return TranscriptionResult(
            provider="openai",
            model=model,
            language=language,
            language_confidence=None,
            duration=total,
            segments=clean_segments(segments),
            usage=[
                Usage(
                    kind="transcription_minutes",
                    quantity=minutes,
                    estimated_cost=transcription_cost(model, minutes),
                    model=model,
                )
            ],
        )

    def _transcribe_in_chunks(
        self, audio: Path, total: float, on_progress: Callable[[int], None]
    ) -> tuple[list[TranscriptSegment], str | None]:
        length = float(self.settings.openai_chunk_seconds)
        overlap = self.settings.openai_chunk_overlap_seconds
        count = math.ceil(total / length)
        segments: list[TranscriptSegment] = []
        language: str | None = None

        for index in range(count):
            start = index * length
            chunk = audio.with_name(f"{audio.stem}.chunk{index:03d}.mp3")
            cut(audio, chunk, start, length + overlap)
            try:
                response = self._request(chunk)
            finally:
                chunk.unlink(missing_ok=True)

            language = language or language_code(_field(response, "language"))
            last_end = segments[-1].end if segments else 0.0
            # The overlap was already transcribed at the end of the previous chunk.
            segments += [s for s in _segments(response, offset=start) if s.start >= last_end - 0.5]
            on_progress(int((index + 1) * 100 / count))

        return segments, language

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=2, max=30), reraise=True)
    def _request(self, path: Path) -> Any:
        with path.open("rb") as handle:
            return self.client.audio.transcriptions.create(
                model=self.settings.openai_transcription_model,
                file=handle,
                response_format="verbose_json",
                timestamp_granularities=["segment"],
            )


def _field(response: Any, name: str) -> Any:
    if isinstance(response, dict):
        return response.get(name)
    return getattr(response, name, None)


def _segments(response: Any, offset: float) -> list[TranscriptSegment]:
    return [
        TranscriptSegment(
            start=float(_field(segment, "start")) + offset,
            end=float(_field(segment, "end")) + offset,
            text=str(_field(segment, "text") or ""),
        )
        for segment in (_field(response, "segments") or [])
    ]
