from fastapi import APIRouter, Depends, HTTPException, status

from app.config import get_settings
from app.pipeline.frames import extract_frame
from app.pipeline.visual import VisualError
from app.schemas.visual import CaptureRequest, CaptureResult
from app.security import require_signature
from app.storage import UnsafePathError, relative, resolve

router = APIRouter(dependencies=[Depends(require_signature)])

# Thumbnails are larger than analysis frames: they are what the catalogue shows.
THUMBNAIL_MAX_HEIGHT = 1080


@router.post("/frames/capture", response_model=CaptureResult)
def capture(body: CaptureRequest) -> CaptureResult:
    """Signed by Laravel: one frame of the master as a JPEG at the given path, either the
    thumbnail ("Use current frame") or a key frame an encoder adds. Synchronous; a single
    frame takes about a second."""
    if body.output_path.startswith(f"records/{body.record_id}/thumbnail/"):
        max_height = THUMBNAIL_MAX_HEIGHT
    elif body.output_path.startswith(f"records/{body.record_id}/frames/"):
        # Added key frames match the ones the pipeline extracts.
        max_height = get_settings().frame_max_height
    else:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "The frame must be stored with its record."
        )
    try:
        source = resolve(body.video_path)
        target = resolve(body.output_path)
    except UnsafePathError as error:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(error)) from error
    if not source.is_file():
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "The video file was not found.")
    try:
        extract_frame(source, target, body.time_sec, max_height)
    except VisualError as error:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(error)) from error
    return CaptureResult(path=relative(target))
