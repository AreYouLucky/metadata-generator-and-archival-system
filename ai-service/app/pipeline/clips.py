"""Clips (Phase 7): a part of the master cut to its own MP4.

Re-encoded (H.264/AAC, never taller than `max_height`, deinterlaced when the master is
interlaced) rather than stream-copied, so the clip starts exactly where asked instead of at
the previous key frame, and plays in every browser and on social platforms.
"""

import subprocess  # noqa: S404 - fixed argv, no shell
from pathlib import Path

from app.pipeline import probe

CLIP_TIMEOUT_SECONDS = 30 * 60


class ClipError(RuntimeError):
    pass


def command(
    source: Path, target: Path, start: float, length: float, max_height: int, interlaced: bool
) -> list[str]:
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
        # Input seeking: fast, and exact because the clip is re-encoded.
        "-ss",
        f"{start:.3f}",
        "-i",
        str(source),
        "-t",
        f"{length:.3f}",
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
        "20",
        "-c:a",
        "aac",
        "-b:a",
        "160k",
        "-ac",
        "2",
        "-movflags",
        "+faststart",
        str(target),
    ]


def cut(source: Path, target: Path, start: float, end: float, max_height: int) -> Path:
    """Write the clip next to its target first, so a failed cut leaves nothing behind."""
    summary = probe.summarise(probe.run_ffprobe(source), probe.run_mediainfo(source))
    duration = summary.get("duration")
    if isinstance(duration, int | float) and start >= duration:
        raise ClipError("The clip starts after the end of the video.")
    interlaced = str(summary.get("scan_type") or "").startswith("interlaced")

    target.parent.mkdir(parents=True, exist_ok=True)
    partial = target.with_name(f".{target.stem}.part.mp4")
    try:
        result = subprocess.run(  # noqa: S603 - fixed argv, paths confined to storage
            command(source, partial, start, end - start, max_height, interlaced),
            capture_output=True,
            timeout=CLIP_TIMEOUT_SECONDS,
            check=False,
        )
    except subprocess.TimeoutExpired as error:
        partial.unlink(missing_ok=True)
        raise ClipError("Cutting the clip timed out.") from error
    if result.returncode != 0 or not partial.is_file() or partial.stat().st_size == 0:
        partial.unlink(missing_ok=True)
        message = result.stderr.decode(errors="replace").strip()
        raise ClipError(f"ffmpeg could not cut the clip: {message[:500] or 'unknown'}")
    partial.replace(target)
    return target
