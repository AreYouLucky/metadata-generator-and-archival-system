"""Frame descriptions and on-screen content in metadata: what is sent (and so paid for),
what is cached, and what is reported as usage. The models are fakes."""

from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import pytest

from app.config import Settings
from app.providers.llm import LlmUsage
from app.schemas.visual import FrameDescription, FrameDescriptions, VisualFrame
from app.services import vision
from app.services.cache import MemoryCache
from app.services.metadata import ON_SCREEN_HEADING, compact_visual
from tests.test_metadata_service import GENERIC, FakeLlm, generator, segments


@dataclass
class FakeVisionLlm:
    calls: list[dict[str, Any]] = field(default_factory=list)
    # Answer with numbers shifted by this much (to test answers that don't fit).
    shift: int = 0

    def complete(self, *, model: str, images: Any = None, **kwargs: Any) -> tuple[Any, LlmUsage]:
        self.calls.append({"model": model, "images": images, **kwargs})
        count = len(images or [])
        return FrameDescriptions(
            frames=[
                FrameDescription(number=number + self.shift, description=f"  Frame   {number} ")
                for number in range(1, count + 1)
            ]
        ), LlmUsage(model=model, input_tokens=300, cached_tokens=0, output_tokens=40)


@pytest.fixture(autouse=True)
def fake_images(monkeypatch: pytest.MonkeyPatch) -> None:
    # The image bytes are the file name: no Pillow needed, and equal names hash equally.
    monkeypatch.setattr(vision, "encode_image", lambda path, size: path.name.encode())


def describer(llm: FakeVisionLlm, cache: MemoryCache | None = None) -> vision.FrameDescriber:
    settings = Settings(vision_model="gpt-6-luna", vision_batch_frames=2)
    return vision.FrameDescriber(settings=settings, llm=llm, cache=cache or MemoryCache())


def test_frames_are_sent_in_small_low_detail_batches() -> None:
    llm = FakeVisionLlm()
    frames = {0: Path("a.jpg"), 2: Path("b.jpg"), 5: Path("c.jpg")}

    descriptions, usage = describer(llm).describe(frames)

    assert [len(call["images"]) for call in llm.calls] == [2, 1]
    assert llm.calls[0]["images"][0].detail == "low"
    assert llm.calls[0]["images"][0].data_url.startswith("data:image/jpeg;base64,")
    assert descriptions == {0: "Frame 1", 2: "Frame 2", 5: "Frame 1"}
    by_kind = {item.kind: item for item in usage}
    assert by_kind["vision_frames"].quantity == 3
    assert by_kind["vision_frames"].estimated_cost is None  # the tokens carry the cost
    assert by_kind["llm_input_tokens"].quantity == 600


def test_a_frame_described_before_is_not_paid_for_again() -> None:
    cache = MemoryCache()
    describer(FakeVisionLlm(), cache).describe({0: Path("a.jpg")})
    llm = FakeVisionLlm()

    descriptions, usage = describer(llm, cache).describe({7: Path("a.jpg")})

    assert llm.calls == []
    assert usage == []
    assert descriptions == {7: "Frame 1"}


def test_numbers_outside_the_batch_are_ignored() -> None:
    descriptions, _ = describer(FakeVisionLlm(shift=5)).describe({0: Path("a.jpg")})

    assert descriptions == {}


def test_on_screen_text_shown_twice_is_listed_once() -> None:
    text = compact_visual(
        [
            VisualFrame(time_sec=65, description="A slide.", ocr_text="Rice  targets"),
            VisualFrame(time_sec=5, description="A title card.", ocr_text="Regional Fair"),
            VisualFrame(time_sec=95, description=None, ocr_text="rice targets"),
            VisualFrame(time_sec=120),
        ],
        6000,
    )

    assert text == (
        '[0:05] A title card. On screen: "Regional Fair"\n[1:05] A slide. On screen: "Rice targets"'
    )


def test_long_on_screen_content_is_shortened_evenly_so_the_end_is_kept() -> None:
    frames = [
        VisualFrame(time_sec=index * 10, ocr_text=f"Slide {index} " + "word " * 100)
        for index in range(30)
    ]

    text = compact_visual(frames, 1500)

    assert len(text) <= 1500
    assert "[4:50] " in text  # the last frame is still there


def test_on_screen_content_is_sent_after_the_transcript_and_changes_the_cache_key() -> None:
    llm = FakeLlm()
    service = generator(llm)
    service.generate(segments(), GENERIC)

    service.generate(
        segments(), GENERIC, visual=[VisualFrame(time_sec=3, ocr_text="Regional S&T Fair")]
    )

    assert len(llm.calls) == 2  # not answered from the transcript-only cache
    messages = llm.calls[1]["messages"]
    assert messages[0].startswith("Transcript:\n")
    assert messages[1] == ON_SCREEN_HEADING + '[0:03] On screen: "Regional S&T Fair"'


def test_metadata_can_be_written_from_on_screen_content_alone() -> None:
    llm = FakeLlm()

    result = generator(llm).generate(
        [], GENERIC, duration=90, visual=[VisualFrame(time_sec=3, description="A slide.")]
    )

    assert llm.calls[0]["messages"][0] == "Transcript:\n(nothing is said)"
    assert result.metadata.quotes == []  # quotes only come from what is said
