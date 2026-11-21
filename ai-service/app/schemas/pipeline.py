"""Request and hand-over shapes for the processing pipeline (PROJECT_SPEC §6)."""

from typing import Literal

from pydantic import BaseModel, Field

from app.schemas.common import PresetRules, TranscriptSegment

StageName = Literal["checksum", "probe", "extract_audio", "transcribe", "generate_metadata"]

# Phase 1 stages, in run order. Checksums come first: nothing else touches the file before.
STAGES: tuple[StageName, ...] = (
    "checksum",
    "probe",
    "extract_audio",
    "transcribe",
    "generate_metadata",
)

# A stage is skipped when any stage it depends on failed or was skipped.
DEPENDS_ON: dict[StageName, tuple[StageName, ...]] = {
    "checksum": (),
    "probe": (),
    "extract_audio": ("probe",),
    "transcribe": ("extract_audio",),
    "generate_metadata": ("transcribe",),
}


class JobOptions(BaseModel):
    preset: PresetRules
    custom_prompt: str | None = Field(default=None, max_length=2000)
    title_count: int = Field(default=3, ge=1, le=10)
    max_duration_seconds: float | None = Field(default=None, gt=0)


# Crockford base32 ULID in either case: Laravel's HasUlids stores them lowercase.
# Kept as sent, because the IDs are echoed back on the callbacks.
ULID_PATTERN = r"^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$"


class ProcessRequest(BaseModel):
    record_id: str = Field(pattern=ULID_PATTERN)
    record_file_id: str = Field(pattern=ULID_PATTERN)
    # Relative to STORAGE_ROOT; resolved and confined by app.storage.
    video_path: str = Field(min_length=1, max_length=1024)
    options: JobOptions


class PipelineContext(BaseModel):
    """Handed from stage to stage through the Celery chain (JSON-serialised)."""

    request: ProcessRequest
    failed: list[StageName] = []
    skipped: list[StageName] = []
    duration: float | None = None
    audio_path: str | None = None
    language: str | None = None
    segments: list[TranscriptSegment] = []

    def blocked_by(self, stage: StageName) -> StageName | None:
        """The first dependency that failed or was skipped, if any."""
        for dependency in DEPENDS_ON[stage]:
            if dependency in self.failed or dependency in self.skipped:
                return dependency
        return None
