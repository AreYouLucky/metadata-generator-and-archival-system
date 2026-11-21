"""Stage `probe`: ffprobe + mediainfo -> `record_technical_metadata` (PROJECT_SPEC §11).

Scan type, bit depth and chroma come from ffprobe (`field_order`, `bits_per_raw_sample`,
`pix_fmt`), fall back to mediainfo, and stay `unknown`/null rather than guessed.
"""

import json
import re
import subprocess  # noqa: S404 - fixed argv, no shell
from pathlib import Path
from typing import Any

FFPROBE_TIMEOUT_SECONDS = 120

# ffprobe field_order: first letter = coded first, second = displayed first.
_FIELD_ORDER_TO_SCAN = {
    "progressive": "progressive",
    "tt": "interlaced_tff",
    "bt": "interlaced_tff",
    "bb": "interlaced_bff",
    "tb": "interlaced_bff",
}

_CHROMA_BY_PIX_FMT_PREFIX = (
    ("yuvj420p", "4:2:0"),
    ("yuvj422p", "4:2:2"),
    ("yuvj444p", "4:4:4"),
    ("yuv420p", "4:2:0"),
    ("yuv422p", "4:2:2"),
    ("yuv444p", "4:4:4"),
    ("yuv411p", "4:1:1"),
    ("yuv440p", "4:4:0"),
    ("nv12", "4:2:0"),
    ("nv21", "4:2:0"),
    ("p010", "4:2:0"),
    ("p210", "4:2:2"),
    ("v210", "4:2:2"),
)


class ProbeError(RuntimeError):
    pass


def run_ffprobe(path: Path) -> dict[str, Any]:
    completed = subprocess.run(  # noqa: S603 - fixed argv, path is confined to storage
        [  # noqa: S607 - ffprobe from the image PATH
            "ffprobe",
            "-v",
            "error",
            "-print_format",
            "json",
            "-show_format",
            "-show_streams",
            str(path),
        ],
        capture_output=True,
        timeout=FFPROBE_TIMEOUT_SECONDS,
        check=False,
    )
    if completed.returncode != 0:
        error = completed.stderr.decode(errors="replace").strip()[:500]
        raise ProbeError(f"ffprobe could not read the file: {error or 'unknown error'}")
    data: dict[str, Any] = json.loads(completed.stdout or b"{}")
    return data


def run_mediainfo(path: Path) -> dict[str, Any]:
    """Best effort: mediainfo is a fallback, so a failure here never fails the stage."""
    try:
        from pymediainfo import MediaInfo

        data: dict[str, Any] = MediaInfo.parse(str(path)).to_data()
        return data
    except Exception:  # noqa: BLE001 - optional source
        return {}


def summarise(ffprobe: dict[str, Any], mediainfo: dict[str, Any]) -> dict[str, Any]:
    """Map raw probe output onto the record_technical_metadata columns."""
    fmt: dict[str, Any] = ffprobe.get("format") or {}
    streams: list[dict[str, Any]] = ffprobe.get("streams") or []
    video = next(
        (
            s
            for s in streams
            if s.get("codec_type") == "video"
            and not (s.get("disposition") or {}).get("attached_pic")
        ),
        None,
    )
    audio_streams = [s for s in streams if s.get("codec_type") == "audio"]
    audio = audio_streams[0] if audio_streams else None
    mi_video = _mediainfo_track(mediainfo, "Video")

    result: dict[str, Any] = {
        "file_format": fmt.get("format_long_name") or fmt.get("format_name"),
        "duration": _float(fmt.get("duration")),
        "bitrate": _int(fmt.get("bit_rate")),
        "start_timecode": _start_timecode(fmt, streams),
        "video_codec": None,
        "video_profile": None,
        "resolution": None,
        "frame_rate": None,
        "aspect_ratio": None,
        "scan_type": None,
        "bit_depth": None,
        "chroma_subsampling": None,
        "color_space": None,
        "color_primaries": None,
        "color_transfer": None,
        "audio_codec": audio.get("codec_name") if audio else None,
        "audio_channels": _int(audio.get("channels")) if audio else None,
        "audio_sample_rate": _int(audio.get("sample_rate")) if audio else None,
        "audio_tracks": [
            {
                "index": position + 1,
                "codec": stream.get("codec_name"),
                "channels": _int(stream.get("channels")),
                "sampleRate": _int(stream.get("sample_rate")),
                "language": (stream.get("tags") or {}).get("language"),
            }
            for position, stream in enumerate(audio_streams)
        ],
        "container_tags": _container_tags(fmt),
    }

    if video:
        pix_fmt = str(video.get("pix_fmt") or "")
        width, height = video.get("width"), video.get("height")
        result.update(
            {
                "video_codec": video.get("codec_name"),
                "video_profile": video.get("profile"),
                "resolution": f"{width}x{height}" if width and height else None,
                "frame_rate": _frame_rate(video),
                "aspect_ratio": _aspect_ratio(video),
                "scan_type": _scan_type(video, mi_video),
                "bit_depth": _bit_depth(video, mi_video),
                "chroma_subsampling": _chroma(pix_fmt, mi_video),
                "color_space": _clean(video.get("color_space")),
                "color_primaries": _clean(video.get("color_primaries")),
                "color_transfer": _clean(video.get("color_transfer")),
            }
        )

    if result["duration"] is None and video:
        result["duration"] = _float(video.get("duration"))

    return result


def _mediainfo_track(mediainfo: dict[str, Any], kind: str) -> dict[str, Any]:
    for track in mediainfo.get("tracks") or []:
        if isinstance(track, dict) and track.get("track_type") == kind:
            return track
    return {}


def _start_timecode(fmt: dict[str, Any], streams: list[dict[str, Any]]) -> str:
    candidates = [(fmt.get("tags") or {}).get("timecode")]
    candidates += [(s.get("tags") or {}).get("timecode") for s in streams]
    for value in candidates:
        if isinstance(value, str) and re.fullmatch(r"\d{2}:\d{2}:\d{2}[:;.]\d{2,3}", value):
            return value
    return "00:00:00:00"


def _container_tags(fmt: dict[str, Any]) -> dict[str, str]:
    """Hints for later phases (date recorded, GPS). Suggestions only, never trusted."""
    tags = fmt.get("tags") or {}
    wanted = {
        "creation_time": tags.get("creation_time"),
        "location": tags.get("location") or tags.get("com.apple.quicktime.location.ISO6709"),
    }
    return {key: str(value) for key, value in wanted.items() if value}


def _frame_rate(video: dict[str, Any]) -> str | None:
    for key in ("r_frame_rate", "avg_frame_rate"):
        value = video.get(key)
        if isinstance(value, str) and value not in ("0/0", ""):
            return value
    return None


def _aspect_ratio(video: dict[str, Any]) -> str | None:
    value = video.get("display_aspect_ratio")
    if isinstance(value, str) and value not in ("0:1", "N/A"):
        return value
    width, height = video.get("width"), video.get("height")
    if width and height:
        from math import gcd

        divisor = gcd(int(width), int(height))
        return f"{int(width) // divisor}:{int(height) // divisor}"
    return None


def _scan_type(video: dict[str, Any], mi_video: dict[str, Any]) -> str:
    field_order = video.get("field_order")
    if isinstance(field_order, str) and field_order in _FIELD_ORDER_TO_SCAN:
        return _FIELD_ORDER_TO_SCAN[field_order]
    scan = str(mi_video.get("scan_type") or "").lower()
    order = str(mi_video.get("scan_order") or "").upper()
    if scan == "progressive":
        return "progressive"
    if scan in ("interlaced", "mbaff"):
        if order.startswith("TFF"):
            return "interlaced_tff"
        if order.startswith("BFF"):
            return "interlaced_bff"
    return "unknown"


def _bit_depth(video: dict[str, Any], mi_video: dict[str, Any]) -> int | None:
    depth = _int(video.get("bits_per_raw_sample"))
    if depth:
        return depth
    match = re.search(r"p(\d{2})(le|be)$", str(video.get("pix_fmt") or ""))
    if match:
        return int(match.group(1))
    return _int(mi_video.get("bit_depth"))


def _chroma(pix_fmt: str, mi_video: dict[str, Any]) -> str | None:
    for prefix, chroma in _CHROMA_BY_PIX_FMT_PREFIX:
        if pix_fmt.startswith(prefix):
            return chroma
    value = mi_video.get("chroma_subsampling")
    return str(value) if value else None


def _clean(value: Any) -> str | None:
    return str(value) if value not in (None, "", "unknown") else None


def _int(value: Any) -> int | None:
    try:
        return int(float(value))
    except (TypeError, ValueError):
        return None


def _float(value: Any) -> float | None:
    try:
        return float(value)
    except (TypeError, ValueError):
        return None
