from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from app.pipeline.tasks import start_pipeline
from app.schemas.pipeline import ProcessRequest
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
