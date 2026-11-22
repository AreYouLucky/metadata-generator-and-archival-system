"""Translate a transcript line by line, in batches, keeping every line's id.

Token budget: each batch sends "index|text" lines only (no timestamps, no JSON per input
line) after short static instructions, and asks for {index, text} back. Lines the model
skips are asked for once more; if they are still missing, the source text is kept so the
subtitles never lose a line.
"""

from collections.abc import Callable
from dataclasses import dataclass

from app.config import Settings, get_settings
from app.providers.llm import LlmProvider, LlmUsage, get_llm_provider
from app.schemas.common import Usage
from app.schemas.translation import LlmTranslation, SourceLine, TranslatedLine
from app.services.metadata import render, usage_records


@dataclass
class TranslationResult:
    model: str
    lines: list[TranslatedLine]
    usage: list[Usage]
    # Lines left in the source language because the model never returned them.
    kept: int


class Translator:
    def __init__(self, settings: Settings | None = None, llm: LlmProvider | None = None) -> None:
        self.settings = settings or get_settings()
        self.llm = llm or get_llm_provider(self.settings)

    def translate(
        self,
        lines: list[SourceLine],
        target_name: str,
        source_language: str | None = None,
        on_progress: Callable[[int], None] | None = None,
    ) -> TranslationResult:
        model = self.settings.llm_translation_model
        instructions = render("translate_system.j2")
        target = (
            f"Source language: {source_language or 'detect it from the lines'}.\n"
            f"Target language: {target_name}."
        )
        batches = batch_lines(
            lines, self.settings.translation_batch_lines, self.settings.translation_batch_chars
        )
        usages: list[LlmUsage] = []
        translated: dict[int, str] = {}

        for done, batch in enumerate(batches, start=1):
            missing = batch
            for _attempt in range(2):
                answer, usage = self.llm.complete(
                    model=model,
                    instructions=instructions,
                    messages=[target, _numbered(missing)],
                    schema=LlmTranslation,
                    reasoning_effort=self.settings.llm_translation_reasoning_effort,
                    max_output_tokens=self.settings.llm_max_output_tokens,
                )
                usages.append(usage)
                by_index = {item.index: " ".join(item.text.split()) for item in answer.lines}
                for position, line in enumerate(missing, start=1):
                    text = by_index.get(position)
                    if text:
                        translated[line.id] = text
                missing = [line for line in missing if line.id not in translated]
                if not missing:
                    break
            if on_progress is not None:
                on_progress(round(done / len(batches) * 100))

        result = [
            TranslatedLine(id=line.id, text=translated.get(line.id, line.text)) for line in lines
        ]
        kept = sum(1 for line in lines if line.id not in translated)
        return TranslationResult(model, result, usage_records(usages), kept)


def batch_lines(lines: list[SourceLine], max_lines: int, max_chars: int) -> list[list[SourceLine]]:
    batches: list[list[SourceLine]] = [[]]
    size = 0
    for line in lines:
        if batches[-1] and (len(batches[-1]) >= max_lines or size + len(line.text) > max_chars):
            batches.append([])
            size = 0
        batches[-1].append(line)
        size += len(line.text)
    return [batch for batch in batches if batch]


def _numbered(lines: list[SourceLine]) -> str:
    """Indexes restart at 1 per batch: short numbers, and the model can't confuse batches."""
    return "\n".join(
        f"{position}|{' '.join(line.text.split())}" for position, line in enumerate(lines, start=1)
    )
