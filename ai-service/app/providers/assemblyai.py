"""AssemblyAI's REST API (v2), used for hosted transcription and for diarization.

Upload the audio (raw bytes, `authorization` header without "Bearer"), create a
transcript with `speech_models` (a fallback list), language detection and optionally
speaker labels, poll until it is `completed` or `error`, then read its sentences
(start/end in milliseconds, with `speaker` when labels were asked for). No SDK: a few
httpx calls do it, with retries on network errors and 5xx.
"""

import time
from collections.abc import Callable, Iterator
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import httpx
from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

from app.config import Settings, get_settings

UPLOAD_CHUNK_BYTES = 1024 * 1024


class AssemblyAIError(Exception):
    """A problem worth showing the encoder as-is (no keys or internals in the message)."""


@dataclass(frozen=True)
class Sentence:
    start: float
    end: float
    text: str
    speaker: str | None


@dataclass(frozen=True)
class AssemblyAITranscript:
    model: str
    language: str | None
    language_confidence: float | None
    duration: float
    sentences: list[Sentence]


def _retryable(error: BaseException) -> bool:
    if isinstance(error, httpx.HTTPStatusError):
        return error.response.status_code == 429 or error.response.status_code >= 500
    return isinstance(error, httpx.TransportError)


class AssemblyAIClient:
    def __init__(
        self,
        settings: Settings | None = None,
        transport: httpx.BaseTransport | None = None,
        sleep: Callable[[float], None] = time.sleep,
    ) -> None:
        self.settings = settings or get_settings()
        key = self.settings.assemblyai_api_key.get_secret_value()
        if not key:
            raise AssemblyAIError("Set ASSEMBLYAI_API_KEY in ai-service/.env to use AssemblyAI.")
        self._client = httpx.Client(
            base_url=self.settings.assemblyai_base_url,
            headers={"authorization": key},
            timeout=httpx.Timeout(60.0, connect=10.0),
            transport=transport,
        )
        self._sleep = sleep

    def transcribe(
        self,
        audio: Path,
        *,
        speaker_labels: bool,
        duration: float | None,
        on_progress: Callable[[int], None],
    ) -> AssemblyAITranscript:
        models = [m.strip() for m in self.settings.assemblyai_speech_models.split(",") if m.strip()]
        try:
            upload_url = self._upload(audio)
            on_progress(25)
            created = self._json(
                "POST",
                "/v2/transcript",
                json={
                    "audio_url": upload_url,
                    "speech_models": models,
                    "language_detection": True,
                    "speaker_labels": speaker_labels,
                },
            )
            transcript = self._wait(str(created["id"]), duration, on_progress)
            sentences = self._json("GET", f"/v2/transcript/{transcript['id']}/sentences")
        except httpx.HTTPStatusError as error:
            raise _explain(error) from error
        except httpx.TransportError as error:
            raise AssemblyAIError("AssemblyAI could not be reached. Retry later.") from error
        finally:
            self._client.close()

        on_progress(99)
        return AssemblyAITranscript(
            model=str(
                transcript.get("speech_model_used") or transcript.get("speech_model") or models[0]
            ),
            language=_language(transcript.get("language_code")),
            language_confidence=_probability(transcript.get("language_confidence")),
            duration=float(transcript.get("audio_duration") or duration or 0.0),
            sentences=[
                Sentence(
                    start=float(item["start"]) / 1000,
                    end=float(item["end"]) / 1000,
                    text=str(item.get("text") or ""),
                    speaker=str(item["speaker"]) if item.get("speaker") else None,
                )
                for item in sentences.get("sentences") or []
            ],
        )

    def _upload(self, audio: Path) -> str:
        def chunks() -> Iterator[bytes]:
            with audio.open("rb") as handle:
                while chunk := handle.read(UPLOAD_CHUNK_BYTES):
                    yield chunk

        response = self._client.post(
            "/v2/upload",
            content=chunks(),
            headers={"content-type": "application/octet-stream"},
            timeout=httpx.Timeout(600.0, connect=10.0),
        )
        response.raise_for_status()
        return str(response.json()["upload_url"])

    def _wait(
        self, transcript_id: str, duration: float | None, on_progress: Callable[[int], None]
    ) -> dict[str, Any]:
        started = time.monotonic()
        # AssemblyAI usually needs well under a third of the audio's length.
        expected = max(30.0, (duration or 600.0) / 3)
        while True:
            transcript = self._json("GET", f"/v2/transcript/{transcript_id}")
            status = transcript.get("status")
            if status == "completed":
                return transcript
            if status == "error":
                reason = str(transcript.get("error") or "unknown error")[:300]
                raise AssemblyAIError(f"AssemblyAI could not transcribe the audio: {reason}")
            waited = time.monotonic() - started
            if waited > self.settings.assemblyai_max_wait_seconds:
                raise AssemblyAIError("AssemblyAI took too long to transcribe the audio.")
            on_progress(min(95, 25 + int(70 * waited / expected)))
            self._sleep(self.settings.assemblyai_poll_seconds)

    @retry(
        retry=retry_if_exception(_retryable),
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=2, max=30),
        reraise=True,
    )
    def _json(self, method: str, path: str, **kwargs: Any) -> dict[str, Any]:
        response = self._client.request(method, path, **kwargs)
        response.raise_for_status()
        data = response.json()
        if not isinstance(data, dict):
            raise AssemblyAIError("AssemblyAI returned an unexpected answer.")
        return data


def _explain(error: httpx.HTTPStatusError) -> AssemblyAIError:
    status = error.response.status_code
    if status in (401, 403):
        return AssemblyAIError("AssemblyAI rejected the API key. Check ASSEMBLYAI_API_KEY.")
    if status == 429:
        return AssemblyAIError("AssemblyAI's rate limit was reached. Retry later.")
    return AssemblyAIError(f"AssemblyAI returned an error (HTTP {status}).")


def _language(value: Any) -> str | None:
    """AssemblyAI codes look like `en_us`; store the base language like the other providers."""
    if not value:
        return None
    return str(value).split("_")[0].lower() or None


def _probability(value: Any) -> float | None:
    try:
        return round(min(max(float(value), 0.0), 1.0), 4)
    except (TypeError, ValueError):
        return None
