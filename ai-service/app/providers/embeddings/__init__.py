from typing import Protocol

from app.config import Settings, get_settings


class EmbeddingProvider(Protocol):
    def embed(
        self, texts: list[str], *, model: str, dimensions: int
    ) -> tuple[list[list[float]], int]:
        """One vector per text, in order, and the input tokens billed."""
        ...


def get_embedding_provider(settings: Settings | None = None) -> EmbeddingProvider:
    settings = settings or get_settings()
    from app.providers.embeddings.openai_embeddings import OpenAIEmbeddingProvider

    return OpenAIEmbeddingProvider(settings)


__all__ = ["EmbeddingProvider", "get_embedding_provider"]
