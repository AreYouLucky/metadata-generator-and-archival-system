"""Visual analysis shapes (PROJECT_SPEC §6 stages 7-11, the `visual` block of §10).

Face counts only: nothing here identifies people (PROJECT_SPEC §14).
"""

from pydantic import BaseModel, Field

from app.schemas.common import ULID_PATTERN


class Shot(BaseModel):
    """A detected shot, in seconds from the start of the video."""

    start: float = Field(ge=0)
    end: float = Field(ge=0)


class DetectedObject(BaseModel):
    label: str
    confidence: float = Field(ge=0, le=1)


class FrameInfo(BaseModel):
    """A key frame as it travels through the pipeline context."""

    time_sec: float = Field(ge=0)
    # Relative to STORAGE_ROOT.
    path: str
    is_scene_start: bool = False
    # Index of the earlier frame this one looks the same as (a slide held on screen):
    # OCR, objects and descriptions are taken from that frame instead of paid for again.
    duplicate_of: int | None = None
    description: str | None = None
    ocr_text: str | None = None


class VisualFrame(BaseModel):
    """What one frame shows, as sent to the metadata model."""

    time_sec: float = Field(ge=0)
    description: str | None = Field(default=None, max_length=1000)
    ocr_text: str | None = Field(default=None, max_length=5000)


class FrameDescription(BaseModel):
    """One entry of the vision model's structured answer."""

    number: int
    description: str


class FrameDescriptions(BaseModel):
    frames: list[FrameDescription]


THUMBNAIL_PATH_PATTERN = (
    r"^records/[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}/thumbnail/[A-Za-z0-9_-]{1,64}\.jpg$"
)


class CaptureRequest(BaseModel):
    """POST /frames/capture: one frame of the master as the record's thumbnail."""

    record_id: str = Field(pattern=ULID_PATTERN)
    video_path: str = Field(min_length=1, max_length=1024)
    time_sec: float = Field(ge=0)
    output_path: str = Field(pattern=THUMBNAIL_PATH_PATTERN)


class CaptureResult(BaseModel):
    path: str
