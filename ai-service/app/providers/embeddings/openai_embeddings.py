"""OpenAI embeddings (`text-embedding-3-*`, which accept a smaller `dimensions`)."""

from typing import Any

import openai
from openai import OpenAI
from tenacity import retry, retry_if_exception_type, stop_after_attempt, wait_exponential

from app.config import Settings, get_settings

_TRANSIENT = (
    openai.APIConnectionError,
    openai.APITimeoutError,
    openai.RateLimitError,
    openai.InternalServerError,
)


class OpenAIEmbeddingProvider:
    def __init__(self, settings: Settings | None = None, client: Any = None) -> None:
        self.settings = settings or get_settings()
        self.client = client or OpenAI(
            api_key=self.settings.openai_api_key.get_secret_value(),
            timeout=self.settings.openai_timeout_seconds,
        )

    @retry(
        retry=retry_if_exception_type(_TRANSIENT),
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=1, max=30),
        reraise=True,
    )
    def embed(
        self, texts: list[str], *, model: str, dimensions: int
    ) -> tuple[list[list[float]], int]:
        response = self.client.embeddings.create(
            model=model, input=texts, dimensions=dimensions, encoding_format="float"
        )
        data = sorted(response.data, key=lambda item: item.index)
        tokens = int(getattr(getattr(response, "usage", None), "prompt_tokens", 0) or 0)
        return [list(item.embedding) for item in data], tokens
