"""Metadata generation: prompt shape, caching, core reuse, map/reduce and limits.

The LLM is a fake that records each call, so the tests can assert what would be
sent (and therefore paid for) without calling OpenAI.
"""

from dataclasses import dataclass, field
from typing import Any

import pytest

from app.config import Settings
from app.providers.llm import LlmUsage
from app.schemas.common import PresetRules, TranscriptSegment
from app.schemas.metadata import (
    Chapter,
    ChunkSummary,
    GeneratedMetadata,
    PresetCopy,
)
from app.services.cache import MemoryCache
from app.services.metadata import MetadataGenerator, compact_transcript

GENERIC = PresetRules(
    name="generic", label="Generic", title_max=30, description_max=40, tags_max_count=3
)
YOUTUBE = PresetRules(
    name="youtube",
    label="YouTube",
    title_max=100,
    description_max=5000,
    tags_max_count=15,
    tags_max_chars=500,
    hashtags_max=2,
    chapters=True,
)


@dataclass
class FakeLlm:
    calls: list[dict[str, Any]] = field(default_factory=list)

    def complete(
        self,
        *,
        model: str,
        instructions: str,
        messages: list[str],
        schema: Any,
        reasoning_effort: str,
        max_output_tokens: int,
    ) -> tuple[Any, LlmUsage]:
        self.calls.append({"model": model, "schema": schema, "messages": messages})
        usage = LlmUsage(model=model, input_tokens=1000, cached_tokens=200, output_tokens=100)
        if schema is ChunkSummary:
            return ChunkSummary(
                summary="Part summary.", chapters=[Chapter(start=0, title="Part")]
            ), usage
        copy = {
            "titles": ["A title that is far too long for the generic preset", "Short title"],
            "description": "A description that is definitely longer than forty characters.",
            "tags": ["#Science", "fair", "science", "dost"],
            "hashtags": ["#STFair", "Science Fair", "#extra"],
        }
        if schema is PresetCopy:
            return PresetCopy(**copy), usage
        return GeneratedMetadata(
            **copy,
            summary="Officials open the fair.",
            keywords=["DOST", "dost", "science fair"],
            chapters=[
                Chapter(start=5, title="Opening"),
                Chapter(start=8, title="Too close"),
                Chapter(start=60, title="Speeches"),
                Chapter(start=9999, title="Past the end"),
            ],
        ), usage


def segments(
    count: int = 20, words: str = "Magandang umaga po sa inyong lahat"
) -> list[TranscriptSegment]:
    return [
        TranscriptSegment(start=i * 6.0, end=i * 6.0 + 5, text=f"{words} {i}") for i in range(count)
    ]


def generator(llm: FakeLlm, **overrides: Any) -> MetadataGenerator:
    settings = Settings(llm_model="gpt-6.2-sol", llm_light_model="gpt-6-luna", **overrides)
    return MetadataGenerator(settings=settings, llm=llm, cache=MemoryCache())


def test_segments_are_merged_into_blocks_with_one_short_timestamp() -> None:
    blocks = compact_transcript(segments(10), block_seconds=30)

    assert len(blocks) == 2
    assert blocks[0].startswith("[0:00] Magandang umaga po sa inyong lahat 0 ")
    assert blocks[1].startswith("[0:30] ")


def test_the_first_generation_reads_the_transcript_once_and_enforces_the_preset_limits() -> None:
    llm = FakeLlm()

    result = generator(llm).generate(segments(), GENERIC, title_count=2, duration=120)

    assert len(llm.calls) == 1
    assert llm.calls[0]["schema"] is GeneratedMetadata
    assert llm.calls[0]["messages"][0].startswith("Transcript:\n[0:00]")
    assert "Platform: Generic" in llm.calls[0]["messages"][1]
    metadata = result.metadata
    assert all(len(title) <= 30 for title in metadata.titles)
    assert len(metadata.description) <= 40
    assert metadata.tags == ["science", "fair", "dost"]
    assert metadata.hashtags == []  # the generic preset takes none
    assert metadata.keywords == ["DOST", "science fair"]
    assert [(c.start, c.title) for c in metadata.chapters] == [(0.0, "Opening"), (60.0, "Speeches")]
    assert result.source == "transcript"


def test_usage_is_reported_with_cached_tokens_billed_at_the_cached_rate() -> None:
    result = generator(FakeLlm()).generate(segments(), GENERIC)

    by_kind = {usage.kind: usage for usage in result.usage}
    assert by_kind["llm_input_tokens"].quantity == 1000
    # 800 uncached at $2/M + 200 cached at $0.10/M
    assert by_kind["llm_input_tokens"].estimated_cost == pytest.approx(0.00162)
    assert by_kind["llm_output_tokens"].estimated_cost == pytest.approx(0.001)


def test_an_identical_request_is_answered_from_the_cache_without_tokens() -> None:
    llm = FakeLlm()
    service = generator(llm)
    service.generate(segments(), GENERIC)

    again = service.generate(segments(), GENERIC)

    assert len(llm.calls) == 1
    assert again.cached is True
    assert again.usage == []


def test_switching_preset_writes_only_the_copy_from_the_cached_core() -> None:
    llm = FakeLlm()
    service = generator(llm)
    service.generate(segments(), GENERIC)

    result = service.generate(segments(), YOUTUBE)

    second = llm.calls[1]
    assert second["schema"] is PresetCopy
    assert "Magandang umaga" not in "".join(second["messages"])
    assert second["messages"][0].startswith("Summary:\nOfficials open the fair.")
    assert result.source == "core"
    assert result.metadata.hashtags == ["#STFair", "#ScienceFair"]
    assert result.metadata.summary == "Officials open the fair."


def test_a_fresh_regeneration_skips_the_result_cache_but_still_reuses_the_core() -> None:
    llm = FakeLlm()
    service = generator(llm)
    service.generate(segments(), GENERIC)

    result = service.generate(segments(), GENERIC, fresh=True)

    assert len(llm.calls) == 2
    assert llm.calls[1]["schema"] is PresetCopy
    assert result.cached is False


def test_an_edited_transcript_is_read_again_in_full() -> None:
    llm = FakeLlm()
    service = generator(llm)
    service.generate(segments(), GENERIC)

    service.generate(segments(words="Edited line"), GENERIC)

    assert llm.calls[1]["schema"] is GeneratedMetadata


def test_a_long_transcript_is_condensed_part_by_part_with_the_light_model_first() -> None:
    llm = FakeLlm()

    generator(llm, llm_max_transcript_tokens=200, llm_map_chunk_tokens=120).generate(
        segments(40), GENERIC
    )

    map_calls = [call for call in llm.calls if call["schema"] is ChunkSummary]
    final = llm.calls[-1]
    assert len(map_calls) > 1
    assert all(call["model"] == "gpt-6-luna" for call in map_calls)
    assert final["model"] == "gpt-6.2-sol"
    assert final["schema"] is GeneratedMetadata
    assert final["messages"][0].startswith(f"Transcript:\nPart 1 of {len(map_calls)}:")
