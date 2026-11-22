"""Shapes shared between stages, providers and the Laravel callbacks (mirror PROJECT_SPEC §10)."""

from typing import Literal

from pydantic import BaseModel, Field

# Crockford base32 ULID in either case: Laravel's HasUlids stores them lowercase.
# Kept as sent, because the IDs are echoed back on the callbacks.
ULID_PATTERN = r"^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$"

UsageKind = Literal[
    "transcription_minutes", "llm_input_tokens", "llm_output_tokens", "vision_frames"
]


class TranscriptSegment(BaseModel):
    """One timed line. Every transcription provider is normalised to this."""

    start: float = Field(ge=0)
    end: float = Field(ge=0)
    text: str


class SpeakerTurn(BaseModel):
    """Who speaks when, from diarization. Labels are the provider's own (SPEAKER_00, A...);
    Laravel renumbers them S1, S2... and matches them to the transcript lines."""

    start: float = Field(ge=0)
    end: float = Field(ge=0)
    speaker: str = Field(min_length=1, max_length=32)


class Usage(BaseModel):
    """One metered provider call, written by Laravel to `usage_records`."""

    kind: UsageKind
    quantity: float = Field(ge=0)
    estimated_cost: float | None = None
    model: str


class PresetRules(BaseModel):
    """Platform limits, sent by Laravel from config/presets.php (the single source of truth)."""

    name: str
    label: str
    title_max: int | None = None
    description_max: int
    tags_max_count: int | None = None
    tags_max_chars: int | None = None
    hashtags_min: int = 0
    hashtags_max: int = 0
    chapters: bool = False
    guidance: str = ""
