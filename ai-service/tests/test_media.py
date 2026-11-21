"""Probing and audio extraction against small generated videos (needs ffmpeg, as in the image)."""

import shutil
import subprocess  # noqa: S404
from pathlib import Path

import pytest

from app.pipeline.audio import AudioError, extract, waveform_peaks
from app.pipeline.probe import run_ffprobe, run_mediainfo, summarise

pytestmark = pytest.mark.skipif(shutil.which("ffmpeg") is None, reason="ffmpeg is not installed")


def make_video(path: Path, with_audio: bool) -> Path:
    command = ["ffmpeg", "-nostdin", "-loglevel", "error", "-y"]
    command += ["-f", "lavfi", "-i", "testsrc=size=320x240:rate=25:duration=3"]
    if with_audio:
        command += ["-f", "lavfi", "-i", "sine=frequency=440:duration=3"]
    command += ["-c:v", "libx264", "-pix_fmt", "yuv420p"]
    if with_audio:
        command += ["-c:a", "aac", "-shortest"]
    subprocess.run([*command, str(path)], check=True)  # noqa: S603
    return path


def test_a_real_file_is_probed(tmp_path: Path) -> None:
    video = make_video(tmp_path / "clip.mp4", with_audio=True)

    result = summarise(run_ffprobe(video), run_mediainfo(video))

    assert result["video_codec"] == "h264"
    assert result["audio_codec"] == "aac"
    assert result["resolution"] == "320x240"
    assert result["frame_rate"] == "25/1"
    assert result["chroma_subsampling"] == "4:2:0"
    assert result["scan_type"] == "progressive"
    assert result["duration"] == pytest.approx(3.0, abs=0.2)


def test_audio_is_extracted_as_mono_16khz_mp3(tmp_path: Path) -> None:
    video = make_video(tmp_path / "clip.mp4", with_audio=True)
    progress: list[int] = []

    audio = extract(video, tmp_path / "work" / "audio.mp3", 3.0, progress.append)

    stream = run_ffprobe(audio)["streams"][0]
    assert stream["codec_name"] == "mp3"
    assert stream["channels"] == 1
    assert stream["sample_rate"] == "16000"


def test_a_video_without_sound_fails_with_a_clear_message(tmp_path: Path) -> None:
    video = make_video(tmp_path / "silent.mp4", with_audio=False)

    with pytest.raises(AudioError, match="no audio track"):
        extract(video, tmp_path / "audio.mp3", 3.0)


def test_the_waveform_has_ten_peaks_per_second_scaled_to_100(tmp_path: Path) -> None:
    video = make_video(tmp_path / "clip.mp4", with_audio=True)
    audio = extract(video, tmp_path / "audio.mp3", 3.0)

    peaks = waveform_peaks(audio)

    assert 29 <= len(peaks) <= 32
    assert max(peaks) == 100
    assert all(0 <= peak <= 100 for peak in peaks)
