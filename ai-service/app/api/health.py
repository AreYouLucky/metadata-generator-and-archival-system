from fastapi import APIRouter
from pydantic import BaseModel

from app import __version__, gpu_status
from app.config import get_settings

router = APIRouter()


class HealthResponse(BaseModel):
    status: str
    version: str
    gpu: bool
    gpu_name: str | None = None
    # Which providers hear the audio, so the app can say when it leaves the machine.
    transcription_provider: str
    diarization_provider: str


@router.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    """Unsigned so Docker can probe it. Reveals only up/down, version, the GPU model and
    which transcription and diarization providers are configured (no keys or models).

    The GPU is the transcription worker's (this container has none): a running
    worker publishes it to Redis, so it shows as missing when that worker is down.
    """
    gpu_name = gpu_status.read_worker_gpu()
    settings = get_settings()
    return HealthResponse(
        status="ok",
        version=__version__,
        gpu=gpu_name is not None,
        gpu_name=gpu_name,
        transcription_provider=settings.transcription_provider,
        diarization_provider=settings.diarization_provider,
    )
