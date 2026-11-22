"""The access-copy decision and command, and the added-file and fixity tasks."""

import hashlib
from pathlib import Path
from typing import Any

import pytest

from app.config import get_settings
from app.pipeline import files
from app.pipeline.proxy import command, is_browser_playable

RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"
FILE_ID = "01m7z0cwed0mv35vf14kpjeb1e"


@pytest.mark.parametrize(
    ("container", "summary", "playable"),
    [
        ("mov,mp4,m4a,3gp,3g2,mj2", {"video_codec": "h264", "audio_codec": "aac"}, True),
        ("matroska,webm", {"video_codec": "vp9", "audio_codec": "opus"}, True),
        ("mov,mp4,m4a,3gp,3g2,mj2", {"video_codec": "prores", "audio_codec": "pcm_s24le"}, False),
        ("mxf", {"video_codec": "mpeg2video", "audio_codec": "pcm_s24le"}, False),
        ("mov,mp4,m4a,3gp,3g2,mj2", {"video_codec": "h264", "bit_depth": 10}, False),
        (
            "mov,mp4,m4a,3gp,3g2,mj2",
            {"video_codec": "h264", "chroma_subsampling": "4:2:2"},
            False,
        ),
    ],
)
def test_only_what_every_browser_plays_skips_the_access_copy(
    container: str, summary: dict[str, Any], playable: bool
) -> None:
    assert is_browser_playable(container, summary) is playable


def test_the_access_copy_is_deinterlaced_capped_and_starts_fast(tmp_path: Path) -> None:
    argv = command(tmp_path / "in.mxf", tmp_path / "out.mp4", 1080, interlaced=True)

    filters = argv[argv.index("-vf") + 1]
    assert filters.startswith("yadif")
    assert "min(1080,ih)" in filters
    assert argv[argv.index("-c:v") + 1] == "libx264"
    assert "+faststart" in argv


@pytest.fixture
def storage(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> Path:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    return tmp_path


def test_an_added_file_reports_checksums_then_its_probe(
    storage: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    (storage / "records").mkdir()
    (storage / "records" / "mezz.mov").write_bytes(b"mezzanine")
    posted: list[tuple[str, dict[str, Any]]] = []
    monkeypatch.setattr(files, "post_with_retry", lambda path, p: posted.append((path, p)))
    monkeypatch.setattr(files.probe, "run_ffprobe", lambda path: {"format": {}, "streams": []})
    monkeypatch.setattr(files.probe, "run_mediainfo", lambda path: {})

    files.run_ingest(
        {
            "record_id": RECORD_ID,
            "record_file_id": FILE_ID,
            "path": "records/mezz.mov",
            "role": "mezzanine",
        }
    )

    base = f"/internal/record-files/{FILE_ID}/results"
    assert posted[0] == (
        f"{base}/checksum",
        {
            "md5": hashlib.md5(b"mezzanine").hexdigest(),  # noqa: S324 - fixity, not security
            "sha256": hashlib.sha256(b"mezzanine").hexdigest(),
            "file_size": 9,
        },
    )
    assert posted[1][0] == f"{base}/probe"
    assert "raw_probe" in posted[1][1]


def test_a_missing_file_is_reported_to_laravel_as_missing(
    storage: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    posted: list[tuple[str, dict[str, Any]]] = []
    monkeypatch.setattr(files, "post_with_retry", lambda path, p: posted.append((path, p)))

    files.run_fixity(
        {"record_file_id": FILE_ID, "path": "records/gone.mov", "triggered_by": "schedule"}
    )

    assert posted == [
        (
            f"/internal/record-files/{FILE_ID}/fixity",
            {"triggered_by": "schedule", "error": "missing"},
        )
    ]
