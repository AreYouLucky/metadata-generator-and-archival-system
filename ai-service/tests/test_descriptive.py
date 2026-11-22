"""'Fill with AI' for the Archive panel: what is sent, and what is kept of the answer."""

from typing import Any

from app.config import Settings
from app.providers.llm import LlmUsage
from app.schemas.descriptive import DescriptiveRequest, EventContext, LlmDescriptive
from app.services.descriptive import DescriptiveSuggester


class FakeLlm:
    def __init__(self, answer: LlmDescriptive) -> None:
        self.answer = answer
        self.messages: list[str] = []

    def complete(self, *, model: str, messages: list[str], **_: Any) -> tuple[Any, LlmUsage]:
        self.messages = messages
        return self.answer, LlmUsage(model=model, input_tokens=400, output_tokens=150)


def answer(**overrides: Any) -> LlmDescriptive:
    return LlmDescriptive(
        **{
            "title": "Opening of the Regional S&T Fair 2026 in Cebu City",
            "alternative_title": "  ",
            "description": "Officials open the fair.",
            "genre": "event coverage",
            "video_type": "Livestream",
            "country_origin": "PH",
            "date_recorded": "2026-07-10",
            "date_precision": "day",
            **overrides,
        }
    )


def request() -> DescriptiveRequest:
    return DescriptiveRequest(
        record_id="r",
        summary="Officials open the fair.",
        chapters=["0:00 Opening"],
        keywords=["DOST"],
        current_title="MVI_0042",
        project=EventContext(
            title="Regional S&T Fair 2026", date="2026-07-10", end_date="2026-07-12"
        ),
        series=EventContext(title="Day 1", date="2026-07-10"),
        genres=["Event Coverage", "Documentary"],
        video_types=["Full Program", "Highlights"],
    )


def suggester(llm: FakeLlm) -> DescriptiveSuggester:
    return DescriptiveSuggester(settings=Settings(llm_model="gpt-6.2-sol"), llm=llm)


def test_the_brief_and_the_records_context_are_sent_not_the_transcript() -> None:
    llm = FakeLlm(answer())

    suggester(llm).suggest(request())

    assert llm.messages[0].startswith("Summary:\nOfficials open the fair.")
    assert "Event (project): Regional S&T Fair 2026, 2026-07-10 to 2026-07-12" in llm.messages[1]
    assert "Session (series): Day 1, 2026-07-10" in llm.messages[1]
    assert "Genres: Event Coverage; Documentary" in llm.messages[1]


def test_only_listed_names_and_real_past_dates_are_kept() -> None:
    result = suggester(FakeLlm(answer())).suggest(request())

    fields = result.fields
    assert fields.genre == "Event Coverage"  # the archive's own spelling
    assert fields.video_type is None  # "Livestream" isn't in the list
    assert fields.alternative_title is None
    assert (fields.date_recorded, fields.date_recorded_precision) == ("2026-07-10", "day")
    assert {usage.kind for usage in result.usage} == {"llm_input_tokens", "llm_output_tokens"}


def test_a_future_or_malformed_date_is_dropped_with_its_precision() -> None:
    for bad in ("2999-01-01", "10 July 2026"):
        fields = suggester(FakeLlm(answer(date_recorded=bad))).suggest(request()).fields
        assert (fields.date_recorded, fields.date_recorded_precision) == (None, None)
