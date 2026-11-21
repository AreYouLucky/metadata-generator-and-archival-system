from app.config import Settings, get_settings
from app.providers.transcription.base import TranscriptionProvider, TranscriptionResult


def get_transcription_provider(settings: Settings | None = None) -> TranscriptionProvider:
    settings = settings or get_settings()
    if settings.transcription_provider == "openai":
        from app.providers.transcription.openai_whisper import OpenAIWhisperProvider

        return OpenAIWhisperProvider(settings)

    from app.providers.transcription.local import LocalWhisperProvider

    return LocalWhisperProvider(settings)


__all__ = ["TranscriptionProvider", "TranscriptionResult", "get_transcription_provider"]
