import os
from collections.abc import Iterator

import pytest

# Configure before any app module reads settings.
os.environ.update(
    {
        "INTERNAL_SHARED_SECRET": "test-secret",
        "LARAVEL_CALLBACK_BASE": "http://laravel.test",
        "REPLAY_GUARD": "memory",
        "REDIS_URL": "memory://",
        "CELERY_RESULT_URL": "cache+memory://",
    }
)

from fastapi.testclient import TestClient  # noqa: E402

from app.config import get_settings  # noqa: E402
from app.main import app  # noqa: E402
from app.security import MemoryReplayGuard, get_replay_guard  # noqa: E402

SECRET = "test-secret"


@pytest.fixture(autouse=True)
def _fresh_settings() -> Iterator[None]:
    get_settings.cache_clear()
    yield
    get_settings.cache_clear()


@pytest.fixture
def client() -> Iterator[TestClient]:
    # Fresh per test, so identical bodies sent within one second don't collide.
    guard = MemoryReplayGuard()
    app.dependency_overrides[get_replay_guard] = lambda: guard
    yield TestClient(app)
    app.dependency_overrides.clear()
