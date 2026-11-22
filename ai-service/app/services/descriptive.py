"""Suggest catalogue fields for a record ("Fill with AI" in the Archive panel).

The model only chooses: genre and video type must be one of the archive's own names, and a
recording date must be a real, past date; anything else comes back as null rather than a
guess. Long text is cut to the catalogue's limits in code, so the model is never asked twice.
"""

from datetime import date

from app.config import Settings, get_settings
from app.providers.llm import LlmProvider, get_llm_provider
from app.schemas.descriptive import (
    DescriptiveRequest,
    DescriptiveResult,
    DescriptiveSuggestion,
    LlmDescriptive,
)
from app.services.metadata import render, truncate, usage_records

TITLE_MAX = 250
DESCRIPTION_MAX = 2000


class DescriptiveSuggester:
    def __init__(self, settings: Settings | None = None, llm: LlmProvider | None = None) -> None:
        self.settings = settings or get_settings()
        self.llm = llm or get_llm_provider(self.settings)

    def suggest(self, request: DescriptiveRequest) -> DescriptiveResult:
        model = self.settings.llm_model
        answer, usage = self.llm.complete(
            model=model,
            instructions=render("descriptive_system.j2"),
            messages=[
                render(
                    "descriptive_brief.j2",
                    summary=request.summary.strip(),
                    chapters=request.chapters,
                    keywords=request.keywords,
                ),
                render("descriptive_request.j2", request=request),
            ],
            schema=LlmDescriptive,
            reasoning_effort=self.settings.llm_reasoning_effort,
            max_output_tokens=self.settings.llm_max_output_tokens,
        )
        return DescriptiveResult(
            model=model,
            fields=clean(answer, request),
            usage=usage_records([usage]),
        )


def clean(answer: LlmDescriptive, request: DescriptiveRequest) -> DescriptiveSuggestion:
    """Keep only what fits the catalogue: listed names, real past dates, limited lengths."""
    alternative = _one_line(answer.alternative_title or "")
    return DescriptiveSuggestion(
        title=truncate(_one_line(answer.title), TITLE_MAX) or request.current_title,
        alternative_title=truncate(alternative, TITLE_MAX) or None,
        description=truncate(answer.description.strip(), DESCRIPTION_MAX),
        genre=_listed(answer.genre, request.genres),
        video_type=_listed(answer.video_type, request.video_types),
        country_origin=truncate(_one_line(answer.country_origin or ""), 64) or None,
        date_recorded=_past_date(answer.date_recorded),
        date_recorded_precision=answer.date_precision if _past_date(answer.date_recorded) else None,
    )


def _one_line(text: str) -> str:
    return " ".join(text.split())


def _listed(name: str | None, names: list[str]) -> str | None:
    """The archive's own spelling of a listed name (case-insensitive), or None."""
    wanted = _one_line(name or "").lower()
    return next((option for option in names if option.lower() == wanted), None)


def _past_date(value: str | None) -> str | None:
    try:
        parsed = date.fromisoformat((value or "").strip())
    except ValueError:
        return None
    return parsed.isoformat() if parsed <= date.today() and parsed.year >= 1900 else None
