import json
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

from app.api import jobs
from app.config import get_settings
from app.security import signed_headers
from tests.conftest import SECRET

# Lowercase, as Laravel's HasUlids generates them.
RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"


def body(video_path: str) -> bytes:
    return json.dumps(
        {
            "record_id": RECORD_ID,
            "record_file_id": RECORD_ID,
            "video_path": video_path,
            "options": {"preset": {"name": "generic", "label": "Generic", "description_max": 2000}},
        }
    ).encode()


def post(client: TestClient, payload: bytes) -> object:
    return client.post(
        "/jobs/process",
        content=payload,
        headers={"Content-Type": "application/json", **signed_headers(SECRET, payload)},
    )


@pytest.fixture
def storage(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> Path:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    return tmp_path


def test_a_signed_request_for_an_existing_file_queues_the_pipeline(
    client: TestClient, storage: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    (storage / "records").mkdir()
    (storage / "records" / "a.mp4").write_bytes(b"video")
    started: list[str] = []
    monkeypatch.setattr(
        jobs, "start_pipeline", lambda request: started.append(request.video_path) or "job-1"
    )

    response = post(client, body("records/a.mp4"))

    assert response.status_code == 202  # type: ignore[attr-defined]
    assert response.json() == {"job_id": "job-1"}  # type: ignore[attr-defined]
    assert started == ["records/a.mp4"]


def test_a_path_outside_the_storage_root_is_refused(client: TestClient, storage: Path) -> None:
    assert post(client, body("../etc/passwd")).status_code == 422  # type: ignore[attr-defined]


def test_a_missing_file_is_refused(client: TestClient, storage: Path) -> None:
    assert post(client, body("records/missing.mp4")).status_code == 422  # type: ignore[attr-defined]


def test_an_unsigned_request_is_refused(client: TestClient) -> None:
    response = client.post("/jobs/process", content=body("records/a.mp4"))

    assert response.status_code == 401
