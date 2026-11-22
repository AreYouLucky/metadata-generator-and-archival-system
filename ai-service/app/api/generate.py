from fastapi import APIRouter, Depends

from app.api.errors import explained
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


class FreshGenerateMetadataRequest(GenerateMetadataRequest):
    # True for an explicit "Regenerate": skips the result cache (the cached core is still used).
    fresh: bool = False


@router.post("/generate/metadata", response_model=GenerateMetadataResult)
def generate_metadata(body: FreshGenerateMetadataRequest) -> GenerateMetadataResult:
    """Signed by Laravel's GenerateMetadataJob (regenerate). Runs synchronously."""
    return explained(
        body.record_id,
        lambda: MetadataGenerator().generate(
            body.transcript,
            body.preset,
            language=body.language,
            custom_prompt=body.custom_prompt,
            title_count=body.title_count,
            duration=body.duration,
            fresh=body.fresh,
            visual=body.visual_context,
        ),
    )


@router.post("/generate/titles", response_model=GenerateTitlesResult)
def generate_titles(body: GenerateTitlesRequest) -> GenerateTitlesResult:
    """Signed by Laravel's GenerateTitlesJob ("Generate more"). Runs synchronously."""
    return explained(
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
    return explained(body.record_id, lambda: DescriptiveSuggester().suggest(body))
