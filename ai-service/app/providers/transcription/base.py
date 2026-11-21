from collections.abc import Callable
from pathlib import Path
from typing import Protocol

from pydantic import BaseModel

from app.schemas.common import TranscriptSegment, Usage


class TranscriptionResult(BaseModel):
    provider: str
    model: str
    language: str | None
    language_confidence: float | None
    duration: float
    segments: list[TranscriptSegment]
    usage: list[Usage]


class TranscriptionProvider(Protocol):
    def transcribe(
        self,
        audio: Path,
        duration: float | None,
        on_progress: Callable[[int], None],
    ) -> TranscriptionResult: ...


def clean_segments(segments: list[TranscriptSegment]) -> list[TranscriptSegment]:
    """Drop empty lines, trim text, keep times ordered and non-negative."""
    cleaned: list[TranscriptSegment] = []
    for segment in sorted(segments, key=lambda s: s.start):
        text = " ".join(segment.text.split())
        if not text:
            continue
        start = max(0.0, round(segment.start, 3))
        end = max(start, round(segment.end, 3))
        cleaned.append(TranscriptSegment(start=start, end=end, text=text))
    return cleaned
