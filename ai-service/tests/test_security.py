import pytest

from app.security import MemoryReplayGuard, sign, signed_headers, verify

BODY = b'{"ping_id":"0b5a5a3e-7c1e-4d3a-9f0e-2a6f4b8c9d10","source":"ai-worker"}'
NOW = 1700000000
# Shared with tests/Unit/Services/InternalSignatureTest.php so both sides provably agree.
KNOWN_SIGNATURE = "sha256=a998dbaa4c5c8ea69f36179186ceefac68ae5f80c86e5b823cd177ee8871f1cb"


def test_it_matches_the_signature_laravel_produces() -> None:
    assert sign("test-secret", str(NOW), BODY) == KNOWN_SIGNATURE
    assert signed_headers("test-secret", BODY, now=NOW) == {
        "X-Timestamp": str(NOW),
        "X-Signature": KNOWN_SIGNATURE,
    }


def test_it_accepts_a_fresh_signature_of_the_exact_body() -> None:
    assert verify("test-secret", str(NOW), KNOWN_SIGNATURE, BODY, 300, now=NOW + 299)


@pytest.mark.parametrize(
    ("timestamp", "signature", "body", "now"),
    [
        pytest.param(
            str(NOW),
            KNOWN_SIGNATURE,
            BODY.replace(b"ai-worker", b"laravel"),
            NOW,
            id="tampered body",
        ),
        pytest.param(str(NOW), KNOWN_SIGNATURE, BODY, NOW + 301, id="too old"),
        pytest.param(str(NOW), KNOWN_SIGNATURE, BODY, NOW - 301, id="too far in the future"),
        pytest.param(None, KNOWN_SIGNATURE, BODY, NOW, id="missing timestamp"),
        pytest.param(str(NOW), None, BODY, NOW, id="missing signature"),
        pytest.param("1700000000.0", KNOWN_SIGNATURE, BODY, NOW, id="non-numeric timestamp"),
        pytest.param(str(NOW), KNOWN_SIGNATURE[7:], BODY, NOW, id="no algorithm prefix"),
    ],
)
def test_it_rejects_invalid_requests(
    timestamp: str | None, signature: str | None, body: bytes, now: int
) -> None:
    assert not verify("test-secret", timestamp, signature, body, 300, now=now)


def test_it_refuses_to_work_without_a_secret() -> None:
    with pytest.raises(RuntimeError):
        verify("", str(NOW), KNOWN_SIGNATURE, BODY, 300, now=NOW)


def test_the_replay_guard_accepts_each_signature_once() -> None:
    guard = MemoryReplayGuard()

    assert guard.first_use(KNOWN_SIGNATURE, 600)
    assert not guard.first_use(KNOWN_SIGNATURE, 600)
