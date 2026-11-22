"""Request and hand-over shapes for the processing pipeline (PROJECT_SPEC §6)."""

from typing import Literal

from pydantic import BaseModel, Field

from app.schemas.common import ULID_PATTERN, PresetRules, SpeakerTurn, TranscriptSegment
from app.schemas.visual import FrameInfo, Shot, VisualFrame

__all__ = ["ULID_PATTERN"]

StageName = Literal[
    "checksum",
    "probe",
    "extract_audio",
    "transcribe",
    "diarize",
    "scenes",
    "frames",
    "vision",
    "ocr",
    "objects",
    "generate_metadata",
    "proxy",
]

# Stages in run order. Checksums come first: nothing else touches the file before.
# After probing, the audio branch (extract_audio, transcribe, diarize) and the visual branch
# (scenes ... objects) run side by side; metadata waits for both. The access copy
# comes last, so a long transcode never delays the transcript.
STAGES: tuple[StageName, ...] = (
    "checksum",
    "probe",
    "extract_audio",
    "transcribe",
    "diarize",
    "scenes",
    "frames",
    "vision",
    "ocr",
    "objects",
    "generate_metadata",
    "proxy",
)

VISUAL_STAGES: tuple[StageName, ...] = ("scenes", "frames", "vision", "ocr", "objects")

# A stage is skipped when any stage it depends on failed or was skipped. Metadata
# checks its own inputs: it can be written from the transcript, the frames, or both.
DEPENDS_ON: dict[StageName, tuple[StageName, ...]] = {
    "checksum": (),
    "probe": (),
    "extract_audio": ("probe",),
    "transcribe": ("extract_audio",),
    "diarize": ("transcribe",),
    "scenes": ("probe",),
    "frames": ("probe", "scenes"),
    "vision": ("frames",),
    "ocr": ("frames",),
    "objects": ("frames",),
    "generate_metadata": (),
    "proxy": ("probe",),
}


class JobOptions(BaseModel):
    preset: PresetRules
    custom_prompt: str | None = Field(default=None, max_length=2000)
    title_count: int = Field(default=3, ge=1, le=10)
    max_duration_seconds: float | None = Field(default=None, gt=0)
    # Chosen on upload (PROJECT_SPEC §6). Off unless Laravel asks for them.
    visual_analysis: bool = False
    ocr: bool = False
    object_detection: bool = False
    # Speaker labels on the transcript (Phase 6).
    diarization: bool = False
    # A retry of one stage runs only these (that stage and those reading its results);
    # the others restore what they would have produced, from the seed or the stored
    # files, without calling Laravel. None runs every stage.
    stages: list[StageName] | None = None


class PipelineSeed(BaseModel):
    """Results Laravel already stored, sent with a partial run for the stages it doesn't
    run again (the service keeps nothing between runs)."""

    language: str | None = None
    segments: list[TranscriptSegment] | None = None
    shots: list[Shot] | None = None
    # Laravel never sends `duplicate_of`; repeats are found again from the images.
    frames: list[FrameInfo] | None = None


class ProcessRequest(BaseModel):
    record_id: str = Field(pattern=ULID_PATTERN)
    record_file_id: str = Field(pattern=ULID_PATTERN)
    # Relative to STORAGE_ROOT; resolved and confined by app.storage.
    video_path: str = Field(min_length=1, max_length=1024)
    options: JobOptions
    seed: PipelineSeed | None = None

    def runs(self, stage: StageName) -> bool:
        """Whether this run does the stage's work and reports it."""
        return self.options.stages is None or stage in self.options.stages


class PipelineContext(BaseModel):
    """Handed from stage to stage through the Celery chain (JSON-serialised)."""

    request: ProcessRequest
    # The job id Laravel stores; POST /jobs/{job_id}/cancel flags it (pipeline.cancellation).
    run_id: str | None = None
    failed: list[StageName] = []
    skipped: list[StageName] = []
    duration: float | None = None
    # ffprobe's format_name and the probe summary's codecs, for the proxy decision.
    container: str | None = None
    playable: bool | None = None
    interlaced: bool = False
    audio_path: str | None = None
    language: str | None = None
    segments: list[TranscriptSegment] = []
    # From a transcription provider that labels speakers itself (AssemblyAI), so the
    # diarize stage needn't call anything.
    speaker_turns: list[SpeakerTurn] = []
    shots: list[Shot] = []
    frames: list[FrameInfo] = []

    def blocked_by(self, stage: StageName) -> StageName | None:
        """The first dependency that failed or was skipped, if any."""
        for dependency in DEPENDS_ON[stage]:
            if dependency in self.failed or dependency in self.skipped:
                return dependency
        return None

    def disabled_reason(self, stage: StageName) -> str | None:
        """Why a stage is switched off for this video, if it is (upload options)."""
        options = self.request.options
        if stage in VISUAL_STAGES and not options.visual_analysis:
            return "Visual analysis was turned off for this video."
        if stage == "ocr" and not options.ocr:
            return "On-screen text recognition was turned off for this video."
        if stage == "objects" and not options.object_detection:
            return "Object detection was turned off for this video."
        if stage == "diarize" and not options.diarization:
            return "Speaker identification was turned off for this video."
        return None

    def visual_frames(self) -> list[VisualFrame]:
        """Frames with something to say, for the metadata model (duplicates left out)."""
        return [
            VisualFrame(
                time_sec=frame.time_sec, description=frame.description, ocr_text=frame.ocr_text
            )
            for frame in self.frames
            if frame.duplicate_of is None and (frame.description or frame.ocr_text)
        ]

    @classmethod
    def join(cls, branches: list["PipelineContext"]) -> "PipelineContext":
        """Merge the audio and visual branches, which ran side by side from one context.

        Each branch only fills its own fields, so a field that differs from the shared
        starting point comes from the branch that set it."""
        merged = branches[0].model_copy(deep=True)
        for branch in branches[1:]:
            for stage in branch.failed:
                if stage not in merged.failed:
                    merged.failed.append(stage)
            for stage in branch.skipped:
                if stage not in merged.skipped:
                    merged.skipped.append(stage)
            for name in ("audio_path", "language", "segments", "speaker_turns", "shots", "frames"):
                value = getattr(branch, name)
                if value:
                    setattr(merged, name, value)
        return merged
