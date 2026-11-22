"""Frame descriptions with a vision model (stage `vision`), on a small token budget.

- Frames are shrunk to VISION_IMAGE_SIZE and sent at low detail.
- Several frames go in one structured call (VISION_BATCH_FRAMES), so the instructions
  are paid once per batch, not per frame.
- Duplicate frames (a slide held on screen) are never sent; the caller copies the
  description of the frame they repeat.
- Each description is cached by the image's hash, so re-running the pipeline on the
  same video doesn't pay again.
"""

import base64
import hashlib
import io
from pathlib import Path

from app.config import Settings, get_settings
from app.pipeline.visual import missing_extra
from app.pricing import input_cost, output_cost
from app.providers.llm import ImageInput, LlmUsage, VisionLlmProvider, get_vision_llm_provider
from app.schemas.common import Usage
from app.schemas.visual import FrameDescriptions
from app.services.cache import Cache, get_cache
from app.services.metadata import render

CACHE_VERSION = "v1"
MAX_DESCRIPTION_CHARS = 300


def encode_image(path: Path, size: int) -> bytes:
    """The frame as a JPEG no larger than `size` on its longest side."""
    try:
        from PIL import Image
    except ImportError as error:
        raise missing_extra(error) from error

    with Image.open(path) as image:
        copy = image.convert("RGB")
        copy.thumbnail((size, size), Image.Resampling.LANCZOS)
        buffer = io.BytesIO()
        copy.save(buffer, format="JPEG", quality=80, optimize=True)
        return buffer.getvalue()


class FrameDescriber:
    def __init__(
        self,
        settings: Settings | None = None,
        llm: VisionLlmProvider | None = None,
        cache: Cache | None = None,
    ) -> None:
        self.settings = settings or get_settings()
        self.llm = llm or get_vision_llm_provider(self.settings)
        self.cache = cache or get_cache(self.settings)

    def describe(self, frames: dict[int, Path]) -> tuple[dict[int, str], list[Usage]]:
        """Descriptions keyed like `frames` (frame index -> image). Frames the model
        skipped are left out."""
        settings = self.settings
        model = settings.vision_model
        descriptions: dict[int, str] = {}
        pending: list[tuple[int, bytes, str]] = []

        for index, path in frames.items():
            image = encode_image(path, settings.vision_image_size)
            image_hash = hashlib.sha256(image).hexdigest()
            key = (
                "vision:"
                + hashlib.sha256(
                    "|".join([CACHE_VERSION, model, settings.vision_detail, image_hash]).encode()
                ).hexdigest()
            )
            if cached := self.cache.get(key):
                descriptions[index] = cached
            else:
                pending.append((index, image, key))

        usages: list[LlmUsage] = []
        sent = 0
        instructions = render("vision_system.j2")
        batch_size = settings.vision_batch_frames
        for start in range(0, len(pending), batch_size):
            batch = pending[start : start + batch_size]
            answer, usage = self.llm.complete(
                model=model,
                instructions=instructions,
                messages=[f"Frames attached: {len(batch)}, numbered 1-{len(batch)}."],
                schema=FrameDescriptions,
                reasoning_effort=settings.vision_reasoning_effort,
                max_output_tokens=settings.vision_max_output_tokens,
                images=[
                    ImageInput(
                        data_url="data:image/jpeg;base64," + base64.b64encode(image).decode(),
                        detail=settings.vision_detail,
                    )
                    for _, image, _ in batch
                ],
            )
            usages.append(usage)
            sent += len(batch)
            for item in answer.frames:
                if not 1 <= item.number <= len(batch):
                    continue
                text = " ".join(item.description.split())[:MAX_DESCRIPTION_CHARS]
                if text:
                    index, _, key = batch[item.number - 1]
                    descriptions[index] = text
                    self.cache.set(key, text, settings.llm_cache_ttl_seconds)

        return descriptions, usage_records(usages, model, sent)


def usage_records(usages: list[LlmUsage], model: str, frames_sent: int) -> list[Usage]:
    """Token usage (which carries the cost) plus the number of frames looked at."""
    input_tokens = sum(usage.input_tokens for usage in usages)
    cached_tokens = sum(usage.cached_tokens for usage in usages)
    output_tokens = sum(usage.output_tokens for usage in usages)
    if frames_sent == 0:
        return []
    return [
        Usage(
            kind="llm_input_tokens",
            quantity=input_tokens,
            estimated_cost=input_cost(model, input_tokens, cached_tokens),
            model=model,
        ),
        Usage(
            kind="llm_output_tokens",
            quantity=output_tokens,
            estimated_cost=output_cost(model, output_tokens),
            model=model,
        ),
        Usage(kind="vision_frames", quantity=frames_sent, estimated_cost=None, model=model),
    ]
