"""Stage `proxy`: a browser-playable access copy for masters a browser can't play
(PROJECT_SPEC §6): H.264 (8-bit 4:2:0) + AAC in MP4, no taller than PROXY_MAX_HEIGHT,
deinterlaced when the master is interlaced, with the index at the front (`faststart`)
so playback starts before the whole file has downloaded.
"""

import re
import subprocess  # noqa: S404 - fixed argv, no shell
from collections.abc import Callable
from pathlib import Path
from typing import Any

PROXY_TIMEOUT_SECONDS = 6 * 60 * 60

_TIME_PATTERN = re.compile(rb"out_time_us=(\d+)")

# What every current browser plays (Chrome, Edge, Firefox, Safari).
PLAYABLE_CONTAINERS = ("mov", "mp4", "m4a", "webm")
PLAYABLE_VIDEO = ("h264", "vp8", "vp9", "av1")
PLAYABLE_AUDIO = ("aac", "mp3", "opus", "vorbis")


class ProxyError(RuntimeError):
    pass


def is_browser_playable(container: str | None, summary: dict[str, Any]) -> bool:
    """`container` is ffprobe's format_name, e.g. "mov,mp4,m4a,3gp,3g2,mj2" or "mxf"."""
    formats = set((container or "").split(","))
    if not formats & set(PLAYABLE_CONTAINERS):
        return False
    video = summary.get("video_codec")
    audio = summary.get("audio_codec")
    if video is not None and video not in PLAYABLE_VIDEO:
        return False
    if audio is not None and audio not in PLAYABLE_AUDIO:
        return False
    # 10-bit or 4:2:2 H.264 (common from cameras) doesn't decode in most browsers.
    if video == "h264" and (
        (summary.get("bit_depth") or 8) > 8
        or summary.get("chroma_subsampling") not in (None, "4:2:0")
    ):
        return False
    return True


def command(source: Path, target: Path, max_height: int, interlaced: bool) -> list[str]:
    filters = (["yadif=mode=send_frame"] if interlaced else []) + [
        # Never upscale; keep the width even (H.264 needs it).
        f"scale=-2:'min({max_height},ih)'",
        "format=yuv420p",
    ]
    return [
        "ffmpeg",
        "-nostdin",
        "-hide_banner",
        "-loglevel",
        "error",
        "-y",
        "-i",
        str(source),
        "-map",
        "0:v:0",
        "-map",
        "0:a:0?",
        "-vf",
        ",".join(filters),
        "-c:v",
        "libx264",
        "-preset",
        "veryfast",
        "-crf",
        "23",
        "-c:a",
        "aac",
        "-b:a",
        "128k",
        "-ac",
        "2",
        "-movflags",
        "+faststart",
        "-progress",
        "pipe:1",
        str(target),
    ]


def transcode(
    source: Path,
    target: Path,
    duration: float | None,
    max_height: int,
    interlaced: bool,
    on_progress: Callable[[int], None] | None = None,
) -> Path:
    target.parent.mkdir(parents=True, exist_ok=True)
    partial = target.with_suffix(".part.mp4")
    process = subprocess.Popen(  # noqa: S603 - fixed argv, paths confined to storage
        command(source, partial, max_height, interlaced),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    assert process.stdout is not None
    for line in process.stdout:
        match = _TIME_PATTERN.search(line)
        if match and duration and on_progress:
            seconds = int(match.group(1)) / 1_000_000
            on_progress(min(90, int(seconds * 90 / duration)))
    try:
        _, stderr = process.communicate(timeout=PROXY_TIMEOUT_SECONDS)
    except subprocess.TimeoutExpired as error:
        process.kill()
        partial.unlink(missing_ok=True)
        raise ProxyError("Making the access copy timed out.") from error
    if process.returncode != 0:
        partial.unlink(missing_ok=True)
        message = stderr.decode(errors="replace").strip()
        raise ProxyError(f"ffmpeg could not make the access copy: {message[:500] or 'unknown'}")
    # Only a finished copy replaces the previous one.
    partial.replace(target)
    return target
