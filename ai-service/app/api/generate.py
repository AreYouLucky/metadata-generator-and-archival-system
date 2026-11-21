import logging

import openai
from fastapi import APIRouter, Depends, HTTPException, status

from app.providers.llm import LlmError
from app.schemas.metadata import GenerateMetadataRequest, GenerateMetadataResult
from app.security import require_signature
from app.services.metadata import MetadataGenerator

router = APIRouter(dependencies=[Depends(require_signature)])
logger = logging.getLogger(__name__)


class FreshGenerateMetadataRequest(GenerateMetadataRequest):
    # True for an explicit "Regenerate": skips the result cache (the cached core is still used).
    fresh: bool = False


@router.post("/generate/metadata", response_model=GenerateMetadataResult)
def generate_metadata(body: FreshGenerateMetadataRequest) -> GenerateMetadataResult:
    """Signed by Laravel's GenerateMetadataJob (regenerate). Runs synchronously."""
    try:
        return MetadataGenerator().generate(
            body.transcript,
            body.preset,
            language=body.language,
            custom_prompt=body.custom_prompt,
            title_count=body.title_count,
            duration=body.duration,
            fresh=body.fresh,
        )
    except LlmError as error:
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, str(error)) from error
    except openai.AuthenticationError as error:
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, "OpenAI rejected the API key.") from error
    except openai.APIError as error:
        logger.warning("record_id=%s OpenAI error: %s", body.record_id, type(error).__name__)
        raise HTTPException(
            status.HTTP_502_BAD_GATEWAY, f"OpenAI returned an error ({type(error).__name__})."
        ) from error
