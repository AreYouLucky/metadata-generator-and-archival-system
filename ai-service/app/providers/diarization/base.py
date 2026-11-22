from collections.abc import Callable
from pathlib import Path
from typing import Protocol

from pydantic import BaseModel

from app.schemas.common import SpeakerTurn, Usage


class DiarizationResult(BaseModel):
    provider: str
    model: str
    turns: list[SpeakerTurn]
    usage: list[Usage] = []


class DiarizationProvider(Protocol):
    def diarize(
        self, audio: Path, duration: float | None, on_progress: Callable[[int], None]
    ) -> DiarizationResult: ...


class DiarizationError(Exception):
    """A problem worth showing the encoder as-is."""
