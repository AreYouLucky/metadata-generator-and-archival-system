"""Jobs on one stored file (PROJECT_SPEC §7): ingest an added file, or re-hash a file for a
fixity check. Laravel compares the checksums; this service only computes them."""

from typing import Literal

from pydantic import BaseModel, Field, model_validator

from app.schemas.common import ULID_PATTERN


class IngestFileRequest(BaseModel):
    record_id: str = Field(pattern=ULID_PATTERN)
    record_file_id: str = Field(pattern=ULID_PATTERN)
    # Relative to STORAGE_ROOT; resolved and confined by app.storage.
    path: str = Field(min_length=1, max_length=1024)
    role: Literal["mezzanine", "access", "proxy"]


AUDIO_EXPORT_PATH_PATTERN = (
    r"^records/[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}/exports/audio\.(mp3|wav)$"
)


class AudioExportRequest(BaseModel):
    """POST /jobs/export-audio: the master's audio as a file to download."""

    record_id: str = Field(pattern=ULID_PATTERN)
    video_path: str = Field(min_length=1, max_length=1024)
    format: Literal["mp3", "wav"]
    output_path: str = Field(pattern=AUDIO_EXPORT_PATH_PATTERN)
    duration: float | None = Field(default=None, ge=0)

    @model_validator(mode="after")
    def _output_matches(self) -> "AudioExportRequest":
        if self.output_path != f"records/{self.record_id}/exports/audio.{self.format}":
            raise ValueError("The export must be stored with its record, named for its format.")
        return self


class FixityRequest(BaseModel):
    record_file_id: str = Field(pattern=ULID_PATTERN)
    path: str = Field(min_length=1, max_length=1024)
    triggered_by: Literal["schedule", "user"]
