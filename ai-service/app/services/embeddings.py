"""Embeddings of transcript passages and chat questions (Phase 7). Laravel stores the vectors
and searches them; the size comes from Laravel so both sides always agree."""

from app.config import Settings, get_settings
from app.pricing import input_cost
from app.providers.embeddings import EmbeddingProvider, get_embedding_provider
from app.providers.llm import LlmError
from app.schemas.chat import EmbedRequest, EmbedResult
from app.schemas.common import Usage


class TextEmbedder:
    def __init__(
        self, settings: Settings | None = None, provider: EmbeddingProvider | None = None
    ) -> None:
        self.settings = settings or get_settings()
        self.provider = provider or get_embedding_provider(self.settings)

    def embed(self, request: EmbedRequest) -> EmbedResult:
        model = self.settings.embedding_model
        vectors, tokens = self.provider.embed(
            request.texts, model=model, dimensions=request.dimensions
        )
        if len(vectors) != len(request.texts) or any(
            len(vector) != request.dimensions for vector in vectors
        ):
            raise LlmError("The embedding model returned vectors of the wrong number or size.")
        usage = (
            [
                Usage(
                    kind="llm_input_tokens",
                    quantity=tokens,
                    estimated_cost=input_cost(model, tokens, 0),
                    model=model,
                )
            ]
            if tokens > 0
            else []
        )
        return EmbedResult(model=model, dimensions=request.dimensions, vectors=vectors, usage=usage)
