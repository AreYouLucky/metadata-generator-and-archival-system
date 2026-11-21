"""Transcript translation (PROJECT_SPEC §7 `/translate`, run as an async job with callbacks).

Only line ids and text travel: Laravel keeps the timings and copies them from the original
line onto each translated one, so the model never sees (or can break) a timestamp."""

from pydantic import BaseModel, Field

from app.schemas.pipeline import ULID_PATTERN

LANGUAGE_PATTERN = r"^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$"


class SourceLine(BaseModel):
    id: int = Field(ge=1)
    text: str = Field(max_length=5000)


class TranslatedLine(BaseModel):
    id: int
    text: str


class TranslateRequest(BaseModel):
    record_id: str = Field(pattern=ULID_PATTERN)
    # The translation's `record_transcripts` row; the callbacks are addressed to it.
    transcript_id: int = Field(ge=1)
    source_language: str | None = Field(default=None, pattern=LANGUAGE_PATTERN)
    target_language: str = Field(pattern=LANGUAGE_PATTERN)
    # e.g. "Filipino": models follow a name more reliably than an ISO code.
    target_language_name: str = Field(min_length=2, max_length=64)
    lines: list[SourceLine] = Field(min_length=1, max_length=50000)


class LlmLine(BaseModel):
    index: int
    text: str


class LlmTranslation(BaseModel):
    """Structured output for one batch."""

    lines: list[LlmLine]
