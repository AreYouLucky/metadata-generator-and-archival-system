import logging
from collections.abc import Callable

import openai
from fastapi import HTTPException, status

from app.providers.llm import LlmError

logger = logging.getLogger(__name__)


def explained[T](record_id: str | None, call: Callable[[], T]) -> T:
    """Provider failures become a 502 whose `detail` Laravel can show the encoder."""
    try:
        return call()
    except LlmError as error:
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, str(error)) from error
    except openai.AuthenticationError as error:
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, "OpenAI rejected the API key.") from error
    except openai.APIError as error:
        logger.warning("record_id=%s OpenAI error: %s", record_id, type(error).__name__)
        raise HTTPException(
            status.HTTP_502_BAD_GATEWAY, f"OpenAI returned an error ({type(error).__name__})."
        ) from error
