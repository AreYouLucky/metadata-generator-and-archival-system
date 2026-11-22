"""Frames, scenes and thumbnails against small generated videos (need ffmpeg; the scene
and image tests also need the `vision` extra and are skipped without it)."""

import json
import shutil
import subprocess  # noqa: S404
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

from app.config import get_settings
from app.pipeline import frames, scenes
from app.security import signed_headers
from tests.conftest import SECRET

pytestmark = pytest.mark.skipif(shutil.which("ffmpeg") is None, reason="ffmpeg is not installed")

RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"


def make_slides(path: Path) -> Path:
    """Three 2-second "slides": red, blue, green."""
    command = ["ffmpeg", "-nostdin", "-loglevel", "error", "-y"]
    for colour in ("red", "blue", "green"):
        command += ["-f", "lavfi", "-i", f"color=c={colour}:s=320x240:r=25:d=2"]
    command += [
        "-filter_complex",
        "[0][1][2]concat=n=3:v=1:a=0,format=yuv420p",
        "-c:v",
        "libx264",
        str(path),
    ]
    subprocess.run(command, check=True)  # noqa: S603
    return path


def test_a_frame_is_taken_at_the_given_time(tmp_path: Path) -> None:
    video = make_slides(tmp_path / "slides.mp4")

    frame = frames.extract_frame(video, tmp_path / "out" / "a.jpg", 3.0, 720)

    assert frame.read_bytes()[:3] == b"\xff\xd8\xff"  # a JPEG
    assert not list((tmp_path / "out").glob(".*part*"))


def test_frames_past_the_end_are_left_out(tmp_path: Path) -> None:
    video = make_slides(tmp_path / "slides.mp4")
    planned = [frames.PlannedFrame(1.0, True), frames.PlannedFrame(60.0, False)]

    taken = frames.extract_all(video, tmp_path / "frames", planned, 720)

    assert [frame.time_sec for frame, _ in taken] == [1.0]


def test_each_slide_is_a_shot(tmp_path: Path) -> None:
    pytest.importorskip("scenedetect")
    video = make_slides(tmp_path / "slides.mp4")

    shots = scenes.detect(video, 6.0, 27.0)

    assert [round(shot.start) for shot in shots] == [0, 2, 4]


def test_repeated_frames_of_one_slide_are_duplicates(tmp_path: Path) -> None:
    pytest.importorskip("PIL")
    video = make_slides(tmp_path / "slides.mp4")
    planned = [frames.PlannedFrame(time, False) for time in (0.5, 1.5, 2.5)]
    taken = frames.extract_all(video, tmp_path / "frames", planned, 720)

    looks = [frames.look(path) for _, path in taken]

    assert frames.duplicates(looks) == [None, 0, None]


def test_a_signed_capture_writes_the_thumbnail_inside_the_record(
    client: TestClient, tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    make_slides(tmp_path / "master.mp4")

    def capture(output_path: str) -> object:
        body = json.dumps(
            {
                "record_id": RECORD_ID,
                "video_path": "master.mp4",
                "time_sec": 3.0,
                "output_path": output_path,
            }
        ).encode()
        return client.post(
            "/frames/capture",
            content=body,
            headers={"Content-Type": "application/json", **signed_headers(SECRET, body)},
        )

    ok = capture(f"records/{RECORD_ID}/thumbnail/abc.jpg")
    key_frame = capture(f"records/{RECORD_ID}/frames/added_abc.jpg")
    other_record = capture("records/01m7z0cwed0mv35vf14kpjeb1e/thumbnail/abc.jpg")
    other_folder = capture(f"records/{RECORD_ID}/master/abc.jpg")

    assert ok.status_code == 200, ok.text
    assert ok.json() == {"path": f"records/{RECORD_ID}/thumbnail/abc.jpg"}
    assert (tmp_path / "records" / RECORD_ID / "thumbnail" / "abc.jpg").is_file()
    assert key_frame.status_code == 200, key_frame.text
    assert (tmp_path / "records" / RECORD_ID / "frames" / "added_abc.jpg").is_file()
    assert other_record.status_code == 422
    assert other_folder.status_code == 422
