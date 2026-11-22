from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from app.pipeline.files import start_fixity, start_ingest
from app.pipeline.tasks import start_pipeline
from app.pipeline.translation import start_translation
from app.schemas.files import FixityRequest, IngestFileRequest
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
