from fastapi import APIRouter, Depends

from app.api.errors import explained
from app.schemas.chat import ChatRequest, ChatResponse, EmbedRequest, EmbedResult
from app.security import require_signature
from app.services.chat import VideoChat
from app.services.embeddings import TextEmbedder

router = APIRouter(dependencies=[Depends(require_signature)])


@router.post("/chat", response_model=ChatResponse)
def chat(body: ChatRequest) -> ChatResponse:
    """One step of the chat tool loop, signed by Laravel's AnswerChatQuestionJob."""
    return explained(body.record_id, lambda: VideoChat().step(body))


@router.post("/embed", response_model=EmbedResult)
def embed(body: EmbedRequest) -> EmbedResult:
    """Vectors for transcript passages or a question (Laravel stores and searches them)."""
    return explained(body.record_id, lambda: TextEmbedder().embed(body))
