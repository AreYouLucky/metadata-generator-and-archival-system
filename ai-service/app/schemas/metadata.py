"""AI metadata shapes. `AiMetadata` mirrors the `ai` block of PROJECT_SPEC §10 (Phase 1 fields)."""

from pydantic import BaseModel, Field

from app.schemas.common import PresetRules, TranscriptSegment, Usage


class Chapter(BaseModel):
    start: float = Field(ge=0)
    title: str


class TranscriptCore(BaseModel):
    """Preset-independent understanding of a transcript. Cached per transcript, so switching
    preset or asking again reads this (~1-2k tokens) instead of the whole transcript."""

    summary: str
    chapters: list[Chapter]
    keywords: list[str]


class PresetCopy(BaseModel):
    """The preset-specific part: titles, description, tags, hashtags."""

    titles: list[str]
    description: str
    tags: list[str]
    hashtags: list[str]


class GeneratedMetadata(TranscriptCore, PresetCopy):
    """Everything in one structured response (used when the transcript is sent)."""


class ChunkSummary(BaseModel):
    """Map step for long transcripts."""

    summary: str
    chapters: list[Chapter]


class AiMetadata(BaseModel):
    titles: list[str]
    description: str
    summary: str
    tags: list[str]
    keywords: list[str]
    hashtags: list[str]
    chapters: list[Chapter]


class GenerateMetadataRequest(BaseModel):
    record_id: str
    transcript: list[TranscriptSegment] = Field(min_length=1)
    language: str | None = None
    preset: PresetRules
    custom_prompt: str | None = Field(default=None, max_length=2000)
    title_count: int = Field(default=3, ge=1, le=10)
    duration: float | None = Field(default=None, ge=0)


class GenerateMetadataResult(BaseModel):
    model: str
    metadata: AiMetadata
    usage: list[Usage]
    cached: bool = False
    # Which input the copy was written from: the full transcript or the cached core.
    source: str = "transcript"
