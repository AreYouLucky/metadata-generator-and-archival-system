from app.config import Settings, get_settings
from app.providers.diarization.base import (
    DiarizationError,
    DiarizationProvider,
    DiarizationResult,
)


def get_diarization_provider(settings: Settings | None = None) -> DiarizationProvider:
    settings = settings or get_settings()
    if settings.diarization_provider == "assemblyai":
        from app.providers.diarization.assemblyai import AssemblyAIDiarizer

        return AssemblyAIDiarizer(settings)

    from app.providers.diarization.pyannote import PyannoteDiarizer

    return PyannoteDiarizer(settings)


__all__ = [
    "DiarizationError",
    "DiarizationProvider",
    "DiarizationResult",
    "get_diarization_provider",
]
