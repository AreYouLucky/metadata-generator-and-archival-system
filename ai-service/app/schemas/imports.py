"""URL import (POST /import-url, Phase 6)."""

from pydantic import BaseModel, Field

from app.schemas.common import ULID_PATTERN


class ImportRequest(BaseModel):
    record_id: str = Field(pattern=ULID_PATTERN)
    # The downloaded file is named after it: {output_dir}/{record_file_id}.{ext}.
    record_file_id: str = Field(pattern=ULID_PATTERN)
    url: str = Field(min_length=8, max_length=2048, pattern=r"^https?://")
    # Relative to STORAGE_ROOT; Laravel sends records/{id}/master.
    output_dir: str = Field(min_length=1, max_length=255)
    max_bytes: int = Field(gt=0)
    max_duration_seconds: int = Field(gt=0)
