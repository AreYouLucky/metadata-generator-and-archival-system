"""Clips: a part of the master cut to its own MP4, checksummed and probed, then reported."""

import hashlib
import json
import shutil
from pathlib import Path
from typing import Any

import pytest
from fastapi.testclient import TestClient
from pydantic import ValidationError

from app.api import jobs as jobs_api
from app.config import get_settings
from app.pipeline import files
from app.pipeline.clips import command
from app.pipeline.probe import run_ffprobe
from app.schemas.files import ClipRequest
from app.security import signed_headers
from tests.conftest import SECRET
from tests.test_media import make_video

RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"

needs_ffmpeg = pytest.mark.skipif(shutil.which("ffmpeg") is None, reason="ffmpeg is not installed")


def request(**overrides: Any) -> dict[str, Any]:
    return {
        "record_id": RECORD_ID,
        "clip_id": 7,
        "video_path": "master.mp4",
        "start": 0.5,
        "end": 2.0,
        "output_path": f"records/{RECORD_ID}/clips/7.mp4",
        "max_height": 720,
        **overrides,
    }


def test_the_cut_is_exact_capped_and_starts_fast(tmp_path: Path) -> None:
    argv = command(tmp_path / "in.mxf", tmp_path / "out.mp4", 12.5, 30.0, 720, interlaced=True)

    assert argv[argv.index("-ss") + 1] == "12.500"
    assert argv.index("-ss") < argv.index("-i")
    assert argv[argv.index("-t") + 1] == "30.000"
    assert argv[argv.index("-vf") + 1].startswith("yadif")
    assert "min(720,ih)" in argv[argv.index("-vf") + 1]
    assert argv[argv.index("-c:v") + 1] == "libx264"
    assert "+faststart" in argv


@pytest.mark.parametrize(
    ("overrides", "message"),
    [
        ({"end": 0.5}, "must end after it starts"),
        ({"start": 0, "end": 3601}, "at most an hour"),
        ({"output_path": f"records/{RECORD_ID}/clips/8.mp4"}, "named for its id"),
        ({"output_path": "records/../secret.mp4"}, "pattern"),
    ],
)
def test_a_clip_request_is_checked(overrides: dict[str, Any], message: str) -> None:
    with pytest.raises(ValidationError, match=message):
        ClipRequest.model_validate(request(**overrides))


@pytest.fixture
def callbacks(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> list[tuple[str, dict[str, Any]]]:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    sent: list[tuple[str, dict[str, Any]]] = []
    monkeypatch.setattr(
        files, "post_with_retry", lambda path, payload: sent.append((path, payload))
    )
    return sent


@needs_ffmpeg
def test_a_finished_clip_reports_its_file_checksums_and_probe(
    callbacks: list[tuple[str, dict[str, Any]]], tmp_path: Path
) -> None:
    make_video(tmp_path / "master.mp4", with_audio=True)

    files.run_clip(request())

    path, payload = callbacks[0]
    clip = tmp_path / payload["path"]
    assert path == f"/internal/records/{RECORD_ID}/clips/7"
    assert payload["file_size"] == clip.stat().st_size > 0
    assert payload["sha256"] == hashlib.sha256(clip.read_bytes()).hexdigest()
    assert payload["probe"]["video_codec"] == "h264"
    assert payload["probe"]["duration"] == pytest.approx(1.5, abs=0.2)
    assert float(run_ffprobe(clip)["format"]["duration"]) == pytest.approx(1.5, abs=0.2)
    assert not list(clip.parent.glob(".*part*"))


@needs_ffmpeg
def test_a_clip_past_the_end_reports_why(
    callbacks: list[tuple[str, dict[str, Any]]], tmp_path: Path
) -> None:
    make_video(tmp_path / "master.mp4", with_audio=True)

    files.run_clip(request(start=50.0, end=60.0))

    assert callbacks == [
        (
            f"/internal/records/{RECORD_ID}/clips/7",
            {"error": "The clip starts after the end of the video."},
        )
    ]
    assert not (tmp_path / "records" / RECORD_ID / "clips" / "7.mp4").exists()


def test_the_clips_endpoint_queues_the_cut(
    client: TestClient, monkeypatch: pytest.MonkeyPatch, tmp_path: Path
) -> None:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    (tmp_path / "master.mp4").write_bytes(b"video")
    queued: list[ClipRequest] = []
    monkeypatch.setattr(jobs_api, "start_clip", lambda body: queued.append(body) or "job-1")
    body = json.dumps(request()).encode()

    response = client.post(
        "/clips",
        content=body,
        headers={"Content-Type": "application/json", **signed_headers(SECRET, body)},
    )

    assert response.status_code == 202
    assert response.json() == {"job_id": "job-1"}
    assert queued[0].clip_id == 7
