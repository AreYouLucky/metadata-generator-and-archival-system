"""AI metadata shapes. `AiMetadata` mirrors the `ai` block of PROJECT_SPEC §10 (camelCase on the
wire, like the rest of that block: `contentFlags`, `sensitiveTopics`)."""

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, model_validator

from app.schemas.common import PresetRules, TranscriptSegment, Usage
from app.schemas.visual import VisualFrame

Tone = Literal[
    "informative",
    "educational",
    "formal",
    "conversational",
    "celebratory",
    "inspirational",
    "promotional",
    "serious",
    "urgent",
    "entertaining",
]


class Chapter(BaseModel):
    start: float = Field(ge=0)
    title: str


class QuoteDraft(BaseModel):
    """A quote as the model returns it: verbatim text and the time of its block."""

    start: float = Field(ge=0)
    text: str


class Quote(BaseModel):
    """A quote aligned to the transcript lines it was taken from."""

    start: float = Field(ge=0)
    end: float = Field(ge=0)
    speaker: str | None = None
    text: str


SubjectType = Literal["person", "organisation", "place", "topic", "event"]


class SubjectSuggestion(BaseModel):
    """A subject named in the video (never inferred from faces)."""

    name: str
    type: SubjectType


class LocationSuggestion(BaseModel):
    """Where the video was recorded, as said in it. Each level is a name or null; Laravel
    matches the names against the Philippine location tables."""

    text: str
    region: str | None
    province: str | None
    city: str | None
    barangay: str | None


class ContentFlags(BaseModel):
    model_config = ConfigDict(
        validate_by_name=True, validate_by_alias=True, serialize_by_alias=True
    )

    profanity: bool
    sensitive_topics: list[str] = Field(alias="sensitiveTopics")


class TranscriptCore(BaseModel):
    """Preset-independent understanding of a transcript. Cached per transcript, so switching
    preset or asking again reads this (~1-2k tokens) instead of the whole transcript."""

    model_config = ConfigDict(
        validate_by_name=True, validate_by_alias=True, serialize_by_alias=True
    )

    summary: str
    chapters: list[Chapter]
    keywords: list[str]
    quotes: list[Quote] = []
    tone: Tone | None = None
    content_flags: ContentFlags | None = Field(default=None, alias="contentFlags")
    subjects: list[SubjectSuggestion] = []
    location: LocationSuggestion | None = None


class PresetCopy(BaseModel):
    """The preset-specific part: titles, description, tags, hashtags."""

    titles: list[str]
    description: str
    tags: list[str]
    hashtags: list[str]


class GeneratedMetadata(PresetCopy):
    """Everything in one structured response (used when the transcript is sent)."""

    model_config = ConfigDict(
        validate_by_name=True, validate_by_alias=True, serialize_by_alias=True
    )

    summary: str
    chapters: list[Chapter]
    keywords: list[str]
    quotes: list[QuoteDraft]
    tone: Tone
    content_flags: ContentFlags = Field(alias="contentFlags")
    subjects: list[SubjectSuggestion]
    location: LocationSuggestion | None


class ChunkSummary(BaseModel):
    """Map step for long transcripts. Quotes are picked here, verbatim, because the reduce
    step only sees these summaries."""

    summary: str
    chapters: list[Chapter]
    quotes: list[QuoteDraft]


class AiMetadata(BaseModel):
    model_config = ConfigDict(
        validate_by_name=True, validate_by_alias=True, serialize_by_alias=True
    )

    titles: list[str]
    description: str
    summary: str
    tags: list[str]
    keywords: list[str]
    hashtags: list[str]
    chapters: list[Chapter]
    quotes: list[Quote] = []
    tone: Tone | None = None
    content_flags: ContentFlags | None = Field(default=None, alias="contentFlags")
    # Phase 3 suggestions; they become unconfirmed archive entries in Laravel.
    suggested_subjects: list[SubjectSuggestion] = Field(default=[], alias="suggestedSubjects")
    suggested_location: LocationSuggestion | None = Field(default=None, alias="suggestedLocation")


class GenerateMetadataRequest(BaseModel):
    record_id: str
    transcript: list[TranscriptSegment] = []
    language: str | None = None
    preset: PresetRules
    custom_prompt: str | None = Field(default=None, max_length=2000)
    title_count: int = Field(default=3, ge=1, le=10)
    duration: float | None = Field(default=None, ge=0)
    # Frame descriptions and on-screen text (Phase 5), when the record has them.
    visual_context: list[VisualFrame] = Field(default=[], max_length=200)

    @model_validator(mode="after")
    def _has_content(self) -> "GenerateMetadataRequest":
        if not self.transcript and not any(
            frame.description or frame.ocr_text for frame in self.visual_context
        ):
            raise ValueError("Send a transcript or on-screen content to describe.")
        return self


class GenerateMetadataResult(BaseModel):
    model: str
    metadata: AiMetadata
    usage: list[Usage]
    cached: bool = False
    # Which input the copy was written from: the full transcript or the cached core.
    source: str = "transcript"


class TitleOptions(BaseModel):
    """Structured output for "generate more" titles."""

    titles: list[str]


class GenerateTitlesRequest(BaseModel):
    record_id: str
    transcript_summary: str = Field(min_length=1, max_length=5000)
    preset: PresetRules
    count: int = Field(default=3, ge=1, le=5)
    # Titles the record already has; the new ones must differ.
    avoid: list[str] = Field(default=[], max_length=30)
    language: str | None = None
    custom_prompt: str | None = Field(default=None, max_length=2000)


class GenerateTitlesResult(BaseModel):
    model: str
    titles: list[str]
    usage: list[Usage]
