"""Catalogue (descriptive) field suggestions for the Archive panel's "Fill with AI".

Written from the active generation's summary, chapters and keywords plus the project and
series the record is filed under, never from the transcript, so a fill costs a few hundred
tokens. Genre and video type are picked from the archive's own lists."""

from typing import Literal

from pydantic import BaseModel, Field

from app.schemas.common import Usage

DatePrecision = Literal["day", "month", "year", "circa"]


class EventContext(BaseModel):
    """The project or series a record is filed under (dates are YYYY-MM-DD)."""

    title: str = Field(max_length=250)
    date: str | None = Field(default=None, pattern=r"^\d{4}-\d{2}-\d{2}$")
    end_date: str | None = Field(default=None, pattern=r"^\d{4}-\d{2}-\d{2}$")
    location: str | None = Field(default=None, max_length=250)


class DescriptiveRequest(BaseModel):
    record_id: str
    summary: str = Field(min_length=1, max_length=5000)
    chapters: list[str] = Field(default=[], max_length=200)
    keywords: list[str] = Field(default=[], max_length=50)
    current_title: str = Field(default="", max_length=250)
    language: str | None = None
    project: EventContext | None = None
    series: EventContext | None = None
    genres: list[str] = Field(default=[], max_length=100)
    video_types: list[str] = Field(default=[], max_length=100)


class LlmDescriptive(BaseModel):
    """Structured output. Null means "the brief doesn't say"."""

    title: str
    alternative_title: str | None
    description: str
    genre: str | None
    video_type: str | None
    country_origin: str | None
    date_recorded: str | None
    date_precision: DatePrecision | None


class DescriptiveSuggestion(BaseModel):
    title: str
    alternative_title: str | None
    description: str
    genre: str | None
    video_type: str | None
    country_origin: str | None
    date_recorded: str | None
    date_recorded_precision: DatePrecision | None


class DescriptiveResult(BaseModel):
    model: str
    fields: DescriptiveSuggestion
    usage: list[Usage]
