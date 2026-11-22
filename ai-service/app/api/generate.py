import logging
from collections.abc import Callable

import openai
from fastapi import APIRouter, Depends, HTTPException, status

from app.providers.llm import LlmError
from app.schemas.descriptive import DescriptiveRequest, DescriptiveResult
from app.schemas.metadata import (
    GenerateMetadataRequest,
    GenerateMetadataResult,
    GenerateTitlesRequest,
    GenerateTitlesResult,
)
from app.security import require_signature
from app.services.descriptive import DescriptiveSuggester
from app.services.metadata import MetadataGenerator

router = APIRouter(dependencies=[Depends(require_signature)])
logger = logging.getLogger(__name__)


class FreshGenerateMetadataRequest(GenerateMetadataRequest):
    # True for an explicit "Regenerate": skips the result cache (the cached core is still used).
    fresh: bool = False


def _explained[T](record_id: str, call: Callable[[], T]) -> T:
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


@router.post("/generate/metadata", response_model=GenerateMetadataResult)
def generate_metadata(body: FreshGenerateMetadataRequest) -> GenerateMetadataResult:
    """Signed by Laravel's GenerateMetadataJob (regenerate). Runs synchronously."""
    return _explained(
        body.record_id,
        lambda: MetadataGenerator().generate(
            body.transcript,
            body.preset,
            language=body.language,
            custom_prompt=body.custom_prompt,
            title_count=body.title_count,
            duration=body.duration,
            fresh=body.fresh,
        ),
    )


@router.post("/generate/titles", response_model=GenerateTitlesResult)
def generate_titles(body: GenerateTitlesRequest) -> GenerateTitlesResult:
    """Signed by Laravel's GenerateTitlesJob ("Generate more"). Runs synchronously."""
    return _explained(
        body.record_id,
        lambda: MetadataGenerator().generate_titles(
            body.transcript_summary,
            body.preset,
            count=body.count,
            avoid=body.avoid,
            language=body.language,
            custom_prompt=body.custom_prompt,
        ),
    )


@router.post("/generate/descriptive", response_model=DescriptiveResult)
def generate_descriptive(body: DescriptiveRequest) -> DescriptiveResult:
    """Signed by Laravel's SuggestDescriptiveJob ("Fill with AI"). Runs synchronously."""
    return _explained(body.record_id, lambda: DescriptiveSuggester().suggest(body))
