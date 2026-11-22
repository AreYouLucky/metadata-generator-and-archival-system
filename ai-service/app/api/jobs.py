from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, status
from pydantic import BaseModel, Field

from app.pipeline import cancellation
from app.pipeline.files import start_audio_export, start_fixity, start_ingest
from app.pipeline.tasks import start_pipeline
from app.pipeline.translation import start_translation
from app.schemas.files import AudioExportRequest, FixityRequest, IngestFileRequest
from app.schemas.pipeline import ProcessRequest
from app.schemas.translation import TranslateRequest
from app.security import require_signature
from app.storage import UnsafePathError, resolve

router = APIRouter(dependencies=[Depends(require_signature)])


class JobAccepted(BaseModel):
    job_id: str


@router.post("/jobs/process", response_model=JobAccepted, status_code=status.HTTP_202_ACCEPTED)
def process(body: ProcessRequest) -> JobAccepted:
    """Signed by Laravel. Queues the stage chain; results arrive as signed callbacks."""
    try:
        path = resolve(body.video_path)
    except UnsafePathError as error:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(error)) from error
    if not path.is_file():
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "The video file was not found.")
    return JobAccepted(job_id=start_pipeline(body))


class CancelRequest(BaseModel):
    # Repeated in the body because only the body is signed: it binds the id to the signature.
    job_id: str = Field(pattern=cancellation.JOB_ID_PATTERN)


class CancelAccepted(BaseModel):
    ok: bool


@router.post("/jobs/{job_id}/cancel", response_model=CancelAccepted)
def cancel(
    job_id: Annotated[str, Path(pattern=cancellation.JOB_ID_PATTERN)],
    body: CancelRequest,
) -> CancelAccepted:
    """Signed by Laravel's CancelProcessingJob. Stops the run's remaining stages; one that
    is running stops at its next progress tick. Idempotent, and unknown ids are accepted
    (the run may have finished already)."""
    if body.job_id != job_id:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "The job id does not match.")
    cancellation.cancel(job_id)
    return CancelAccepted(ok=True)


@router.post("/jobs/translate", response_model=JobAccepted, status_code=status.HTTP_202_ACCEPTED)
def translate(body: TranslateRequest) -> JobAccepted:
    """Signed by Laravel's StartTranslationJob. Translates in the background; the lines
    arrive on the signed translation callbacks."""
    return JobAccepted(job_id=start_translation(body))


def _confined(path: str) -> None:
    try:
        if not resolve(path).is_file():
            raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "The file was not found.")
    except UnsafePathError as error:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(error)) from error


@router.post("/jobs/ingest-file", response_model=JobAccepted, status_code=status.HTTP_202_ACCEPTED)
def ingest_file(body: IngestFileRequest) -> JobAccepted:
    """Signed by Laravel's IngestFileJob: checksum and probe an added file."""
    _confined(body.path)
    return JobAccepted(job_id=start_ingest(body))


@router.post("/jobs/fixity", response_model=JobAccepted, status_code=status.HTTP_202_ACCEPTED)
def fixity(body: FixityRequest) -> JobAccepted:
    """Signed by Laravel's RunFixityCheckJob. A missing file is reported by the task as a
    failed check (not refused here), so the archive learns the file is gone."""
    try:
        resolve(body.path)
    except UnsafePathError as error:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(error)) from error
    return JobAccepted(job_id=start_fixity(body))


@router.post("/jobs/export-audio", response_model=JobAccepted, status_code=status.HTTP_202_ACCEPTED)
def export_audio(body: AudioExportRequest) -> JobAccepted:
    """Signed by Laravel's StartAudioExportJob: the master's audio as MP3 or WAV, in the
    background; the result arrives on /internal/records/{id}/audio-export."""
    _confined(body.video_path)
    try:
        resolve(body.output_path)
    except UnsafePathError as error:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(error)) from error
    return JobAccepted(job_id=start_audio_export(body))
