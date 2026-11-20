from uuid import UUID

from fastapi import APIRouter, Depends, status
from pydantic import BaseModel

from app.security import require_signature
from app.worker import send_ping_callback

router = APIRouter(dependencies=[Depends(require_signature)])


class PingRequest(BaseModel):
    ping_id: UUID


class PingAccepted(BaseModel):
    job_id: str
    ping_id: UUID


@router.post("/ping", response_model=PingAccepted, status_code=status.HTTP_202_ACCEPTED)
def ping(body: PingRequest) -> PingAccepted:
    """Signed by Laravel. Queues a worker task that calls Laravel back, signed."""
    result = send_ping_callback.delay(str(body.ping_id))
    return PingAccepted(job_id=result.id, ping_id=body.ping_id)
