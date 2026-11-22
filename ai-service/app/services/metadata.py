"""Metadata generation with a small token budget (DECISIONS.md, "Token budget").

1. The transcript is sent as ~30 s blocks with one short start time each, not per-segment
   timestamps.
2. Transcripts over LLM_MAX_TRANSCRIPT_TOKENS are condensed part by part with the light model
   first (map), then the metadata is written from the condensed parts (reduce).
3. The first generation also returns a preset-independent "core" (summary, chapters,
   keywords), cached per transcript. Regenerating, or switching preset, writes only the
   preset copy from that core instead of re-reading the whole transcript.
4. Identical requests are answered from the cache (`fresh=True` skips that, but still
   uses the core).
5. Prompts put static instructions first so OpenAI's prompt caching applies.
6. Quotes come back as verbatim text with their block time; code aligns them to the exact
   transcript lines (start/end), so the model never needs per-line timestamps.
"""

import hashlib
import json
import math
import re
from pathlib import Path

from jinja2 import Environment, FileSystemLoader, StrictUndefined

from app.config import Settings, get_settings
from app.pricing import input_cost, output_cost
from app.providers.llm import LlmProvider, LlmUsage, get_llm_provider
from app.schemas.common import PresetRules, TranscriptSegment, Usage
from app.schemas.metadata import (
    AiMetadata,
    Chapter,
    ChunkSummary,
    GeneratedMetadata,
    GenerateMetadataResult,
    GenerateTitlesResult,
    PresetCopy,
    Quote,
    QuoteDraft,
    SubjectSuggestion,
    TitleOptions,
    TranscriptCore,
)
from app.schemas.visual import VisualFrame
from app.services.cache import Cache, get_cache

_templates = Environment(
    loader=FileSystemLoader(Path(__file__).resolve().parent.parent / "prompts"),
    undefined=StrictUndefined,
    autoescape=False,  # noqa: S701 - plain-text prompts, never rendered as HTML
    trim_blocks=True,
    keep_trailing_newline=False,
)

MIN_CHAPTER_GAP_SECONDS = 10.0
MAX_KEYWORDS = 15
MAX_QUOTES = 5
MAX_QUOTE_CHARS = 300
MAX_SENSITIVE_TOPICS = 5
MAX_SUBJECTS = 12
# v2: quotes, tone and content flags (Phase 2); v3: subject and location suggestions.
CACHE_VERSION = "v3"
ON_SCREEN_HEADING = "On-screen content (frame descriptions and text read from the video):\n"


def render(template: str, **context: object) -> str:
    return _templates.get_template(template).render(**context).strip()


def format_time(seconds: float) -> str:
    total = int(seconds)
    hours, rest = divmod(total, 3600)
    minutes, secs = divmod(rest, 60)
    return f"{hours}:{minutes:02d}:{secs:02d}" if hours else f"{minutes}:{secs:02d}"


def compact_transcript(segments: list[TranscriptSegment], block_seconds: float) -> list[str]:
    """Merge segments into blocks of about `block_seconds`, one "[m:ss]" per block."""
    blocks: list[str] = []
    current: list[str] = []
    block_start: float | None = None
    for segment in segments:
        text = " ".join(segment.text.split())
        if not text:
            continue
        if block_start is None:
            block_start = segment.start
        elif segment.start - block_start >= block_seconds:
            blocks.append(f"[{format_time(block_start)}] {' '.join(current)}")
            current, block_start = [], segment.start
        current.append(text)
    if current and block_start is not None:
        blocks.append(f"[{format_time(block_start)}] {' '.join(current)}")
    return blocks


def compact_visual(frames: list[VisualFrame], max_chars: int) -> str:
    """One line per frame with something to say: "[m:ss] description On screen: "text"".

    Text already shown on an earlier frame is left out (a slide shown twice). When the
    lines are over `max_chars`, every line is shortened by the same amount, so the end
    of the video is still covered."""
    lines: list[str] = []
    seen: set[str] = set()
    for frame in sorted(frames, key=lambda item: item.time_sec):
        parts: list[str] = []
        if description := " ".join((frame.description or "").split()):
            parts.append(description)
        text = " ".join((frame.ocr_text or "").split())
        if text and text.lower() not in seen:
            seen.add(text.lower())
            parts.append(f'On screen: "{text}"')
        if parts:
            lines.append(f"[{format_time(frame.time_sec)}] " + " ".join(parts))

    if sum(len(line) + 1 for line in lines) > max_chars:
        per_line = max(40, max_chars // len(lines) - 1)
        lines = [line if len(line) <= per_line else line[: per_line - 1] + "…" for line in lines]
    return "\n".join(lines)[:max_chars]


def estimate_tokens(text: str) -> int:
    """Rough and deliberately high: Filipino tokenises worse than English (~3 chars/token)."""
    return math.ceil(len(text) / 3)


class MetadataGenerator:
    def __init__(
        self,
        settings: Settings | None = None,
        llm: LlmProvider | None = None,
        cache: Cache | None = None,
    ) -> None:
        self.settings = settings or get_settings()
        self.llm = llm or get_llm_provider(self.settings)
        self.cache = cache or get_cache(self.settings)

    def generate(
        self,
        segments: list[TranscriptSegment],
        preset: PresetRules,
        *,
        language: str | None = None,
        custom_prompt: str | None = None,
        title_count: int = 3,
        duration: float | None = None,
        fresh: bool = False,
        visual: list[VisualFrame] | None = None,
    ) -> GenerateMetadataResult:
        model = self.settings.llm_model
        blocks = compact_transcript(segments, self.settings.llm_block_seconds)
        transcript_text = "\n".join(blocks)
        visual_text = compact_visual(visual or [], self.settings.visual_context_max_chars)
        duration = duration or (segments[-1].end if segments else None)
        custom_prompt = (custom_prompt or "").strip() or None

        # On-screen content is part of what was read, so it is part of the cache key
        # (only when there is some, so transcript-only caches stay valid).
        hashed = [CACHE_VERSION, model, language or "", transcript_text]
        if visual_text:
            hashed.append(visual_text)
        transcript_hash = _hash(*hashed)
        result_key = "metadata:result:" + _hash(
            transcript_hash, preset.model_dump_json(), custom_prompt or "", str(title_count)
        )
        core_key = f"metadata:core:{transcript_hash}"

        if not fresh and (cached := self.cache.get(result_key)):
            result = GenerateMetadataResult.model_validate_json(cached)
            return result.model_copy(update={"cached": True, "usage": []})

        request = render(
            "metadata_request.j2",
            preset=preset,
            title_count=title_count,
            duration=duration,
            duration_label=format_time(duration or 0),
            language=language,
            custom_prompt=custom_prompt,
        )
        usages: list[LlmUsage] = []

        if cached_core := self.cache.get(core_key):
            core = TranscriptCore.model_validate_json(cached_core)
            copy, usage = self.llm.complete(
                model=model,
                instructions=render("copy_system.j2"),
                messages=[_core_brief(core), request],
                schema=PresetCopy,
                reasoning_effort=self.settings.llm_reasoning_effort,
                max_output_tokens=self.settings.llm_max_output_tokens,
            )
            usages.append(usage)
            source = "core"
        else:
            body = transcript_text
            if estimate_tokens(transcript_text) > self.settings.llm_max_transcript_tokens:
                body, map_usages = self._condense(blocks)
                usages += map_usages
            on_screen = [ON_SCREEN_HEADING + visual_text] if visual_text else []
            generated, usage = self.llm.complete(
                model=model,
                instructions=render("metadata_system.j2"),
                messages=[f"Transcript:\n{body or '(nothing is said)'}", *on_screen, request],
                schema=GeneratedMetadata,
                reasoning_effort=self.settings.llm_reasoning_effort,
                max_output_tokens=self.settings.llm_max_output_tokens,
            )
            usages.append(usage)
            flags = generated.content_flags
            core = TranscriptCore(
                summary=generated.summary,
                chapters=normalise_chapters(generated.chapters, duration),
                keywords=normalise_list(generated.keywords, MAX_KEYWORDS),
                quotes=align_quotes(generated.quotes, segments),
                tone=generated.tone,
                content_flags=flags.model_copy(
                    update={
                        "sensitive_topics": normalise_list(
                            flags.sensitive_topics, MAX_SENSITIVE_TOPICS
                        )
                    }
                ),
                subjects=normalise_subjects(generated.subjects),
                location=generated.location
                if generated.location and generated.location.text.strip()
                else None,
            )
            copy = PresetCopy.model_validate(generated.model_dump())
            self.cache.set(core_key, core.model_dump_json(), self.settings.llm_cache_ttl_seconds)
            source = "transcript"

        result = GenerateMetadataResult(
            model=model,
            metadata=finalise(core, copy, preset, title_count, duration),
            usage=usage_records(usages),
            source=source,
        )
        self.cache.set(result_key, result.model_dump_json(), self.settings.llm_cache_ttl_seconds)
        return result

    def _condense(self, blocks: list[str]) -> tuple[str, list[LlmUsage]]:
        """Map step: summarise each part with the light model."""
        parts: list[list[str]] = [[]]
        size = 0
        for block in blocks:
            tokens = estimate_tokens(block)
            if parts[-1] and size + tokens > self.settings.llm_map_chunk_tokens:
                parts.append([])
                size = 0
            parts[-1].append(block)
            size += tokens

        usages: list[LlmUsage] = []
        condensed: list[str] = []
        instructions = render("map_system.j2")
        for index, part in enumerate(parts, start=1):
            summary, usage = self.llm.complete(
                model=self.settings.llm_light_model,
                instructions=instructions,
                messages=["\n".join(part)],
                schema=ChunkSummary,
                reasoning_effort=self.settings.llm_light_reasoning_effort,
                max_output_tokens=self.settings.llm_max_output_tokens,
            )
            usages.append(usage)
            chapters = "\n".join(f"[{format_time(c.start)}] {c.title}" for c in summary.chapters)
            quotes = "\n".join(f'[{format_time(q.start)}] "{q.text}"' for q in summary.quotes)
            condensed.append(
                f"Part {index} of {len(parts)}:\n{summary.summary}\n{chapters}"
                + (f"\nQuotable lines:\n{quotes}" if quotes else "")
            )
        return "\n\n".join(condensed), usages

    def generate_titles(
        self,
        summary: str,
        preset: PresetRules,
        *,
        count: int = 3,
        avoid: list[str] | None = None,
        language: str | None = None,
        custom_prompt: str | None = None,
    ) -> GenerateTitlesResult:
        """Generate more: new title options from the summary alone (a few hundred tokens),
        never from the transcript. Not cached: asking again should give new options."""
        model = self.settings.llm_model
        avoid = normalise_list(avoid or [], 30)
        request = render(
            "titles_request.j2",
            preset=preset,
            count=count,
            avoid=avoid,
            language=language,
            custom_prompt=(custom_prompt or "").strip() or None,
        )
        options, usage = self.llm.complete(
            model=model,
            instructions=render("titles_system.j2"),
            messages=[f"Summary:\n{summary.strip()}", request],
            schema=TitleOptions,
            reasoning_effort=self.settings.llm_reasoning_effort,
            max_output_tokens=self.settings.llm_max_output_tokens,
        )
        taken = {title.lower() for title in avoid}
        fresh = [
            truncate(title, preset.title_max)
            for title in normalise_list(options.titles, count + len(avoid))
            if title.lower() not in taken
        ]
        return GenerateTitlesResult(
            model=model,
            titles=[title for title in fresh if title][:count],
            usage=usage_records([usage]),
        )


def _core_brief(core: TranscriptCore) -> str:
    chapters = "\n".join(f"[{format_time(c.start)}] {c.title}" for c in core.chapters)
    return (
        f"Summary:\n{core.summary}\n\nChapters:\n{chapters or '(none)'}\n\n"
        f"Keywords: {', '.join(core.keywords)}"
    )


def finalise(
    core: TranscriptCore,
    copy: PresetCopy,
    preset: PresetRules,
    title_count: int,
    duration: float | None,
) -> AiMetadata:
    """Enforce the preset limits on whatever the model returned."""
    titles = [truncate(t, preset.title_max) for t in normalise_list(copy.titles, title_count)]
    return AiMetadata(
        titles=[t for t in titles if t],
        description=truncate(copy.description.strip(), preset.description_max),
        summary=core.summary.strip(),
        tags=normalise_tags(copy.tags, preset),
        keywords=normalise_list(core.keywords, MAX_KEYWORDS),
        hashtags=normalise_hashtags(copy.hashtags, preset),
        chapters=normalise_chapters(core.chapters, duration),
        quotes=core.quotes,
        tone=core.tone,
        content_flags=core.content_flags,
        suggested_subjects=core.subjects,
        suggested_location=core.location,
    )


def normalise_subjects(subjects: list[SubjectSuggestion]) -> list[SubjectSuggestion]:
    """One entry per name (case-insensitive), at most MAX_SUBJECTS."""
    seen: set[str] = set()
    result: list[SubjectSuggestion] = []
    for subject in subjects:
        name = " ".join(subject.name.split())[:250]
        if name and name.lower() not in seen:
            seen.add(name.lower())
            result.append(SubjectSuggestion(name=name, type=subject.type))
    return result[:MAX_SUBJECTS]


def _words(text: str) -> list[str]:
    return re.findall(r"\w+", text.lower())


def align_quotes(
    drafts: list[QuoteDraft], segments: list[TranscriptSegment], limit: int = MAX_QUOTES
) -> list[Quote]:
    """Find the transcript lines each quote was taken from and use their exact times.

    The model sees one time per ~30 s block, so its `start` is approximate. Near that time,
    the line that best matches the quote's opening (as many of its first words as the line
    has) starts the quote; following lines are added until they hold about as many words
    as the quote. A quote whose opening matches no line is dropped (the model did not take
    it from the transcript)."""
    quotes: list[Quote] = []
    for draft in drafts:
        text = " ".join(draft.text.split()).strip(" \"'“”")[:MAX_QUOTE_CHARS]
        words = _words(text)
        if not words or not segments:
            continue

        def opening_match(index: int, words: list[str] = words) -> int:
            line = _words(segments[index].text)
            return len(set(words[: len(line)]) & set(line))

        nearby = [
            index
            for index, segment in enumerate(segments)
            if draft.start - 45 <= segment.start <= draft.start + 75
        ] or list(range(len(segments)))
        first = max(
            nearby,
            key=lambda index: (opening_match(index), -abs(segments[index].start - draft.start)),
        )
        line_length = len(_words(segments[first].text))
        if opening_match(first) * 2 < min(line_length, len(words)):
            continue
        last, count = first, len(_words(segments[first].text))
        while (
            count < len(words)
            and last + 1 < len(segments)
            and segments[last + 1].start - segments[first].start < 60
        ):
            last += 1
            count += len(_words(segments[last].text))
        quote = Quote(start=segments[first].start, end=segments[last].end, text=text)
        if all(abs(existing.start - quote.start) >= 1 for existing in quotes):
            quotes.append(quote)
    return sorted(quotes, key=lambda quote: quote.start)[:limit]


def normalise_list(values: list[str], limit: int) -> list[str]:
    seen: set[str] = set()
    result: list[str] = []
    for value in values:
        cleaned = " ".join(value.split())
        if cleaned and cleaned.lower() not in seen:
            seen.add(cleaned.lower())
            result.append(cleaned)
    return result[:limit]


def normalise_tags(tags: list[str], preset: PresetRules) -> list[str]:
    cleaned = normalise_list([t.lstrip("#").lower() for t in tags], preset.tags_max_count or 15)
    if preset.tags_max_chars is None:
        return cleaned
    result: list[str] = []
    total = 0
    for tag in cleaned:
        cost = len(tag) + (1 if result else 0)  # comma separator
        if total + cost > preset.tags_max_chars:
            break
        result.append(tag)
        total += cost
    return result


def normalise_hashtags(hashtags: list[str], preset: PresetRules) -> list[str]:
    if preset.hashtags_max <= 0:
        return []
    cleaned = ["#" + re.sub(r"[^\w]", "", h.lstrip("#")) for h in hashtags]
    return normalise_list([h for h in cleaned if len(h) > 1], preset.hashtags_max)


def normalise_chapters(chapters: list[Chapter], duration: float | None) -> list[Chapter]:
    """Sorted, first at 0, at least 10 s apart, inside the video (YouTube's rules)."""
    result: list[Chapter] = []
    for chapter in sorted(chapters, key=lambda c: c.start):
        title = " ".join(chapter.title.split())[:100]
        if not title or (duration is not None and chapter.start >= duration):
            continue
        start = 0.0 if not result else round(chapter.start, 1)
        if result and start - result[-1].start < MIN_CHAPTER_GAP_SECONDS:
            continue
        result.append(Chapter(start=start, title=title))
    return result


def truncate(text: str, limit: int | None) -> str:
    text = text.strip()
    if limit is None or len(text) <= limit:
        return text
    cut = text[: limit - 1].rsplit(" ", 1)[0].rstrip(" ,.;:-")
    return cut + "…"


def usage_records(usages: list[LlmUsage]) -> list[Usage]:
    records: list[Usage] = []
    for usage in usages:
        records.append(
            Usage(
                kind="llm_input_tokens",
                quantity=usage.input_tokens,
                estimated_cost=input_cost(usage.model, usage.input_tokens, usage.cached_tokens),
                model=usage.model,
            )
        )
        records.append(
            Usage(
                kind="llm_output_tokens",
                quantity=usage.output_tokens,
                estimated_cost=output_cost(usage.model, usage.output_tokens),
                model=usage.model,
            )
        )
    return records


def _hash(*parts: str) -> str:
    return hashlib.sha256(json.dumps(parts, ensure_ascii=False).encode()).hexdigest()
