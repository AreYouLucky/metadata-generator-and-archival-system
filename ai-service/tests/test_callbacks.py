import httpx
import pytest

from app.callbacks import post_to_laravel
from app.config import get_settings
from app.security import verify
from tests.conftest import SECRET


def test_callbacks_send_the_exact_signed_bytes_to_laravel() -> None:
    seen: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request)
        return httpx.Response(200, json={"ok": True})

    post_to_laravel(
        "/internal/ping",
        {"ping_id": "abc", "source": "ai-worker"},
        transport=httpx.MockTransport(handler),
    )

    request = seen[0]
    assert str(request.url) == "http://laravel.test/internal/ping"
    assert request.content == b'{"ping_id":"abc","source":"ai-worker"}'
    assert verify(
        SECRET,
        request.headers["X-Timestamp"],
        request.headers["X-Signature"],
        request.content,
        get_settings().signature_max_age_seconds,
    )


def test_a_rejected_callback_raises_so_the_task_can_retry() -> None:
    transport = httpx.MockTransport(lambda request: httpx.Response(401))

    with pytest.raises(httpx.HTTPStatusError):
        post_to_laravel("/internal/ping", {"ping_id": "abc"}, transport=transport)
