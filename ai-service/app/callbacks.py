"""Signed calls from the AI service back to Laravel's /internal routes."""

import json
from typing import Any

import httpx

from app.config import Settings, get_settings
from app.security import signed_headers


def encode(payload: dict[str, Any]) -> bytes:
    """Compact JSON: the exact bytes that are signed are the bytes that are sent."""
    return json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode()


def post_to_laravel(
    path: str,
    payload: dict[str, Any],
    settings: Settings | None = None,
    transport: httpx.BaseTransport | None = None,
) -> httpx.Response:
    """POST a signed JSON body to Laravel. Raises on network errors and non-2xx answers."""
    settings = settings or get_settings()
    body = encode(payload)
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json",
        **signed_headers(settings.internal_shared_secret.get_secret_value(), body),
    }
    with httpx.Client(
        base_url=settings.laravel_callback_base,
        timeout=settings.callback_timeout_seconds,
        transport=transport,
    ) as client:
        response = client.post(path, content=body, headers=headers)
    response.raise_for_status()
    return response
