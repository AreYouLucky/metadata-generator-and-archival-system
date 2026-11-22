"""Jobs on one stored file (PROJECT_SPEC §7): ingest an added file, or re-hash a file for a
fixity check. Laravel compares the checksums; this service only computes them."""

from typing import Literal

from pydantic import BaseModel, Field

from app.schemas.pipeline import ULID_PATTERN


class IngestFileRequest(BaseModel):
    record_id: str = Field(pattern=ULID_PATTERN)
    record_file_id: str = Field(pattern=ULID_PATTERN)
    # Relative to STORAGE_ROOT; resolved and confined by app.storage.
    path: str = Field(min_length=1, max_length=1024)
    role: Literal["mezzanine", "access", "proxy"]


class FixityRequest(BaseModel):
    record_file_id: str = Field(pattern=ULID_PATTERN)
    path: str = Field(min_length=1, max_length=1024)
    triggered_by: Literal["schedule", "user"]
