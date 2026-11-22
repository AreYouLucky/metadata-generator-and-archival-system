"""Speakers from AssemblyAI when transcription runs elsewhere (DIARIZATION_PROVIDER=assemblyai).

AssemblyAI has no diarization-only request: it transcribes again with speaker labels,
and only the sentences' speakers and times are kept. That second transcription is paid
for, so it is recorded as transcription minutes. When AssemblyAI is also the
transcription provider, the diarize stage reuses that request's labels instead.
"""

from collections.abc import Callable
from pathlib import Path

from app.config import Settings, get_settings
from app.pricing import ASSEMBLYAI_SPEAKER_LABELS_PER_MINUTE, transcription_cost
from app.providers.assemblyai import AssemblyAIClient
from app.providers.diarization.base import DiarizationResult
from app.schemas.common import SpeakerTurn, Usage


class AssemblyAIDiarizer:
    def __init__(self, settings: Settings | None = None, client: AssemblyAIClient | None = None):
        self.settings = settings or get_settings()
        self._client = client

    def diarize(
        self, audio: Path, duration: float | None, on_progress: Callable[[int], None]
    ) -> DiarizationResult:
        client = self._client or AssemblyAIClient(self.settings)
        result = client.transcribe(
            audio, speaker_labels=True, duration=duration, on_progress=on_progress
        )
        minutes = round(result.duration / 60, 4)
        base = transcription_cost(result.model, minutes)
        model = f"assemblyai/{result.model}"
        return DiarizationResult(
            provider="assemblyai",
            model=model,
            turns=[
                SpeakerTurn(start=s.start, end=max(s.start, s.end), speaker=s.speaker)
                for s in result.sentences
                if s.speaker
            ],
            usage=[
                Usage(
                    kind="transcription_minutes",
                    quantity=minutes,
                    estimated_cost=None
                    if base is None
                    else base + minutes * ASSEMBLYAI_SPEAKER_LABELS_PER_MINUTE,
                    model=model,
                )
            ],
        )
