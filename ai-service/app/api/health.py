import shutil

from fastapi import APIRouter
from pydantic import BaseModel

from app import __version__

router = APIRouter()


class HealthResponse(BaseModel):
    status: str
    version: str
    gpu: bool


@router.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    """Unsigned so Docker can probe it. Reveals nothing beyond up/down and version."""
    return HealthResponse(
        status="ok", version=__version__, gpu=shutil.which("nvidia-smi") is not None
    )
