"""Audio exports (MP3 or WAV of the master's audio) and the callback they report on."""

import shutil
from pathlib import Path
from typing import Any

import pytest
from pydantic import ValidationError

from app.config import get_settings
from app.pipeline import files
from app.pipeline.audio import AudioError, export
from app.pipeline.probe import run_ffprobe
from app.schemas.files import AudioExportRequest
from tests.test_media import make_video

RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"

needs_ffmpeg = pytest.mark.skipif(shutil.which("ffmpeg") is None, reason="ffmpeg is not installed")


@needs_ffmpeg
@pytest.mark.parametrize(
    ("audio_format", "codec", "channels"), [("mp3", "mp3", 2), ("wav", "pcm_s16le", 1)]
)
def test_the_audio_is_exported_in_the_chosen_format(
    tmp_path: Path, audio_format: str, codec: str, channels: int
) -> None:
    video = make_video(tmp_path / "clip.mp4", with_audio=True)

    target = export(video, tmp_path / "exports" / f"audio.{audio_format}", audio_format, 3.0)

    stream = run_ffprobe(target)["streams"][0]
    assert stream["codec_name"] == codec
    assert stream["channels"] == channels  # MP3 is stereo; WAV keeps the master's channels
    assert not list((tmp_path / "exports").glob(".*part*"))


@needs_ffmpeg
def test_a_video_without_sound_has_no_audio_to_export(tmp_path: Path) -> None:
    video = make_video(tmp_path / "silent.mp4", with_audio=False)

    with pytest.raises(AudioError, match="no audio track"):
        export(video, tmp_path / "audio.mp3", "mp3")

    assert not (tmp_path / "audio.mp3").exists()


def test_the_export_must_be_stored_with_its_record_and_named_for_its_format() -> None:
    with pytest.raises(ValidationError):
        AudioExportRequest(
            record_id=RECORD_ID,
            video_path="records/x/master/y.mp4",
            format="mp3",
            output_path=f"records/{RECORD_ID}/exports/audio.wav",
        )
    with pytest.raises(ValidationError):
        AudioExportRequest(
            record_id=RECORD_ID,
            video_path="records/x/master/y.mp4",
            format="mp3",
            output_path="records/01m7z0cwed0mv35vf14kpjeb1e/exports/audio.mp3",
        )


@pytest.fixture
def callbacks(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> list[tuple[str, dict[str, Any]]]:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    sent: list[tuple[str, dict[str, Any]]] = []
    monkeypatch.setattr(
        files, "post_with_retry", lambda path, payload: sent.append((path, payload))
    )
    return sent


def request(audio_format: str = "mp3") -> dict[str, Any]:
    return {
        "record_id": RECORD_ID,
        "video_path": "master.mp4",
        "format": audio_format,
        "output_path": f"records/{RECORD_ID}/exports/audio.{audio_format}",
        "duration": 3.0,
    }


@needs_ffmpeg
def test_a_finished_export_reports_its_path_and_size(
    callbacks: list[tuple[str, dict[str, Any]]], tmp_path: Path
) -> None:
    make_video(tmp_path / "master.mp4", with_audio=True)

    files.run_audio_export(request())

    path, payload = callbacks[0]
    assert path == f"/internal/records/{RECORD_ID}/audio-export"
    assert payload["format"] == "mp3"
    assert payload["path"] == f"records/{RECORD_ID}/exports/audio.mp3"
    assert payload["file_size"] == (tmp_path / payload["path"]).stat().st_size > 0


@needs_ffmpeg
def test_a_failed_export_reports_why(
    callbacks: list[tuple[str, dict[str, Any]]], tmp_path: Path
) -> None:
    make_video(tmp_path / "master.mp4", with_audio=False)

    files.run_audio_export(request("wav"))

    assert callbacks == [
        (
            f"/internal/records/{RECORD_ID}/audio-export",
            {"format": "wav", "error": "The video has no audio track."},
        )
    ]
