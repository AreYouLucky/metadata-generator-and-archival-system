"""AssemblyAI as the transcription provider (TRANSCRIPTION_PROVIDER=assemblyai, optional).

The audio is uploaded to AssemblyAI. Its sentences become the transcript lines. When the
encoder asked for speakers, the same request labels them, so the diarize stage costs
nothing extra (the turns travel in the pipeline context).
"""

from collections.abc import Callable
from pathlib import Path

from app.config import Settings, get_settings
from app.pricing import ASSEMBLYAI_SPEAKER_LABELS_PER_MINUTE, transcription_cost
from app.providers.assemblyai import AssemblyAIClient
from app.providers.transcription.base import TranscriptionResult, clean_segments
from app.schemas.common import SpeakerTurn, TranscriptSegment, Usage


class AssemblyAIProvider:
    supports_speaker_labels = True

    def __init__(self, settings: Settings | None = None, client: AssemblyAIClient | None = None):
        self.settings = settings or get_settings()
        self._client = client

    def transcribe(
        self,
        audio: Path,
        duration: float | None,
        on_progress: Callable[[int], None],
        speaker_labels: bool = False,
    ) -> TranscriptionResult:
        client = self._client or AssemblyAIClient(self.settings)
        result = client.transcribe(
            audio, speaker_labels=speaker_labels, duration=duration, on_progress=on_progress
        )
        minutes = round(result.duration / 60, 4)
        base = transcription_cost(result.model, minutes)
        cost = (
            None
            if base is None
            else base + (minutes * ASSEMBLYAI_SPEAKER_LABELS_PER_MINUTE if speaker_labels else 0.0)
        )
        return TranscriptionResult(
            provider="assemblyai",
            model=f"assemblyai/{result.model}",
            language=result.language,
            language_confidence=result.language_confidence,
            duration=result.duration,
            segments=clean_segments(
                [TranscriptSegment(start=s.start, end=s.end, text=s.text) for s in result.sentences]
            ),
            speaker_turns=[
                SpeakerTurn(start=s.start, end=max(s.start, s.end), speaker=s.speaker)
                for s in result.sentences
                if s.speaker
            ],
            usage=[
                Usage(
                    kind="transcription_minutes",
                    quantity=minutes,
                    estimated_cost=cost,
                    model=f"assemblyai/{result.model}",
                )
            ],
        )
