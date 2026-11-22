"""Stage `ocr`: on-screen text per frame with EasyOCR (PROJECT_SPEC §6).

Runs on the CPU (DECISIONS.md, Phase 5). The reader and its models (about 100 MB,
downloaded into MODELS_DIR on first use) are loaded once per worker process.
"""

import threading
from collections.abc import Sequence
from pathlib import Path
from typing import Any

from app.config import Settings, get_settings
from app.pipeline.visual import VisualError, missing_extra

MAX_TEXT_CHARS = 2000

_reader: Any = None
_lock = threading.Lock()


def _get_reader(settings: Settings) -> Any:
    global _reader
    with _lock:
        if _reader is None:
            try:
                import easyocr
            except ImportError as error:
                raise missing_extra(error) from error
            languages = [code.strip() for code in settings.ocr_languages.split(",") if code.strip()]
            directory = Path(settings.models_dir) / "easyocr"
            try:
                _reader = easyocr.Reader(
                    languages or ["en"],
                    gpu=False,
                    model_storage_directory=str(directory),
                    user_network_directory=str(directory / "user_network"),
                    verbose=False,
                )
            except Exception as error:  # noqa: BLE001 - download or model errors
                raise VisualError(
                    f"The text recognition model could not be loaded ({type(error).__name__}). "
                    "The first run downloads it, so the AI service needs internet access once."
                ) from error
        return _reader


def read(path: Path, settings: Settings | None = None) -> str:
    settings = settings or get_settings()
    results = _get_reader(settings).readtext(str(path), detail=1, paragraph=False)
    return lines_from(results, settings.ocr_min_confidence)


Box = Sequence[Sequence[float]]


def lines_from(results: Sequence[tuple[Box, str, float]], min_confidence: float) -> str:
    """EasyOCR's word boxes as reading-order lines: boxes whose vertical centres are
    within half a box height of each other form one line, read left to right."""
    words: list[tuple[float, float, float, str]] = []
    for box, text, confidence in results:
        text = " ".join(str(text).split())
        # Junk read from textures is short; a misread title still has words in it.
        if float(confidence) < min_confidence or sum(char.isalnum() for char in text) < 2:
            continue
        ys = [float(point[1]) for point in box]
        xs = [float(point[0]) for point in box]
        top, bottom = min(ys), max(ys)
        words.append(((top + bottom) / 2, max(bottom - top, 1.0), min(xs), text))

    lines: list[list[tuple[float, float, float, str]]] = []
    for word in sorted(words, key=lambda item: item[0]):
        if lines:
            line = lines[-1]
            centre = sum(item[0] for item in line) / len(line)
            height = sum(item[1] for item in line) / len(line)
            if abs(word[0] - centre) <= height / 2:
                line.append(word)
                continue
        lines.append([word])

    text = "\n".join(
        " ".join(item[3] for item in sorted(line, key=lambda item: item[2])) for line in lines
    )
    return text[:MAX_TEXT_CHARS].strip()
