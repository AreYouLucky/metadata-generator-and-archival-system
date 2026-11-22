"""Stage `extract_audio`: ffmpeg -> mono 16 kHz MP3 at ~48 kbps (PROJECT_SPEC §6).

Whisper works at 16 kHz mono, so nothing is lost, and an hour of audio is ~21 MB.
"""

import re
import subprocess  # noqa: S404 - fixed argv, no shell
from array import array
from collections.abc import Callable
from pathlib import Path

EXTRACT_TIMEOUT_SECONDS = 60 * 60

# Waveform for the workspace timeline: decoded at 8 kHz, one peak per 100 ms.
WAVEFORM_SAMPLE_RATE = 8000
WAVEFORM_PEAKS_PER_SECOND = 10

_TIME_PATTERN = re.compile(rb"out_time_us=(\d+)")


class AudioError(RuntimeError):
    pass


def extract(
    source: Path,
    target: Path,
    duration: float | None,
    on_progress: Callable[[int], None] | None = None,
) -> Path:
    target.parent.mkdir(parents=True, exist_ok=True)
    command = [
        "ffmpeg",
        "-nostdin",
        "-hide_banner",
        "-loglevel",
        "error",
        "-y",
        "-i",
        str(source),
        "-map",
        "0:a:0",
        "-vn",
        "-ac",
        "1",
        "-ar",
        "16000",
        "-c:a",
        "libmp3lame",
        "-b:a",
        "48k",
        "-progress",
        "pipe:1",
        str(target),
    ]
    process = subprocess.Popen(  # noqa: S603 - fixed argv, paths confined to storage
        command, stdout=subprocess.PIPE, stderr=subprocess.PIPE
    )
    assert process.stdout is not None
    for line in process.stdout:
        match = _TIME_PATTERN.search(line)
        if match and duration and on_progress:
            seconds = int(match.group(1)) / 1_000_000
            on_progress(min(99, int(seconds * 100 / duration)))
    try:
        _, stderr = process.communicate(timeout=EXTRACT_TIMEOUT_SECONDS)
    except subprocess.TimeoutExpired as error:
        process.kill()
        raise AudioError("Audio extraction timed out.") from error

    if process.returncode != 0:
        message = stderr.decode(errors="replace").strip()
        if "matches no streams" in message:
            raise AudioError("The video has no audio track, so it can't be transcribed.")
        raise AudioError(f"ffmpeg could not extract the audio: {message[:500] or 'unknown error'}")
    return target


# Audio exports for download (not the transcription copy above): MP3 at 192 kbps stereo,
# or uncompressed 16-bit WAV keeping the master's channels and sample rate.
EXPORT_CODECS: dict[str, list[str]] = {
    "mp3": ["-c:a", "libmp3lame", "-b:a", "192k", "-ac", "2"],
    "wav": ["-c:a", "pcm_s16le"],
}


def export(
    source: Path,
    target: Path,
    audio_format: str,
    duration: float | None = None,
    on_progress: Callable[[int], None] | None = None,
) -> Path:
    """The master's first audio track as a file to download. Written next to the target
    first, so a failed or interrupted export never replaces a finished one."""
    target.parent.mkdir(parents=True, exist_ok=True)
    partial = target.with_name(f".{target.stem}.part{target.suffix}")
    command = [
        "ffmpeg",
        "-nostdin",
        "-hide_banner",
        "-loglevel",
        "error",
        "-y",
        "-i",
        str(source),
        "-map",
        "0:a:0",
        "-vn",
        *EXPORT_CODECS[audio_format],
        "-progress",
        "pipe:1",
        str(partial),
    ]
    process = subprocess.Popen(  # noqa: S603 - fixed argv, paths confined to storage
        command, stdout=subprocess.PIPE, stderr=subprocess.PIPE
    )
    assert process.stdout is not None
    for line in process.stdout:
        match = _TIME_PATTERN.search(line)
        if match and duration and on_progress:
            on_progress(min(99, int(int(match.group(1)) / 1_000_000 * 100 / duration)))
    try:
        _, stderr = process.communicate(timeout=EXTRACT_TIMEOUT_SECONDS)
    except subprocess.TimeoutExpired as error:
        process.kill()
        partial.unlink(missing_ok=True)
        raise AudioError("Exporting the audio timed out.") from error

    if process.returncode != 0:
        partial.unlink(missing_ok=True)
        message = stderr.decode(errors="replace").strip()
        if "matches no streams" in message:
            raise AudioError("The video has no audio track.")
        raise AudioError(f"ffmpeg could not export the audio: {message[:500] or 'unknown error'}")
    partial.replace(target)
    return target


def waveform_peaks(audio: Path) -> list[int]:
    """Peak level (0-100) per 100 ms, so the browser can draw the waveform without
    downloading or decoding the media. An hour is 36,000 small integers."""
    bucket = WAVEFORM_SAMPLE_RATE // WAVEFORM_PEAKS_PER_SECOND
    process = subprocess.Popen(  # noqa: S603 - fixed argv, path confined to storage
        [  # noqa: S607 - ffmpeg from the image PATH
            "ffmpeg",
            "-nostdin",
            "-loglevel",
            "error",
            "-i",
            str(audio),
            "-ac",
            "1",
            "-ar",
            str(WAVEFORM_SAMPLE_RATE),
            "-f",
            "s16le",
            "pipe:1",
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL,
    )
    assert process.stdout is not None
    raw: list[int] = []
    while chunk := process.stdout.read(bucket * 2):
        samples = array("h")
        samples.frombytes(chunk[: len(chunk) // 2 * 2])
        raw.append(max(max(samples, default=0), -min(samples, default=0)))
    if process.wait(timeout=EXTRACT_TIMEOUT_SECONDS) != 0:
        raise AudioError("ffmpeg could not read the audio for the waveform.")

    loudest = max(raw, default=0) or 1
    return [round(peak * 100 / loudest) for peak in raw]


def cut(source: Path, target: Path, start: float, length: float) -> Path:
    """Copy a slice of the extracted MP3 (for providers with an upload limit)."""
    completed = subprocess.run(  # noqa: S603 - fixed argv
        [  # noqa: S607 - ffmpeg from the image PATH
            "ffmpeg",
            "-nostdin",
            "-hide_banner",
            "-loglevel",
            "error",
            "-y",
            "-ss",
            f"{start:.3f}",
            "-t",
            f"{length:.3f}",
            "-i",
            str(source),
            "-c",
            "copy",
            str(target),
        ],
        capture_output=True,
        timeout=600,
        check=False,
    )
    if completed.returncode != 0:
        raise AudioError(completed.stderr.decode(errors="replace").strip()[:500])
    return target
