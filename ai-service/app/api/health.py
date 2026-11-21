from fastapi import APIRouter
from pydantic import BaseModel

from app import __version__, gpu_status

router = APIRouter()


class HealthResponse(BaseModel):
    status: str
    version: str
    gpu: bool
    gpu_name: str | None = None


@router.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    """Unsigned so Docker can probe it. Reveals only up/down, version and the GPU model.

    The GPU is the transcription worker's (this container has none): a running
    worker publishes it to Redis, so it shows as missing when that worker is down.
    """
    gpu_name = gpu_status.read_worker_gpu()
    return HealthResponse(
        status="ok", version=__version__, gpu=gpu_name is not None, gpu_name=gpu_name
    )
