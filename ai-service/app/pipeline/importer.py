"""URL import with yt-dlp (PROJECT_SPEC §7, POST /import-url; Phase 6).

The video is downloaded into the record's master folder as {record_file_id}.{ext}, up to
IMPORT_MAX_HEIGHT, preferring MP4/H.264 so browsers can play it without a proxy, within
the upload size and duration limits. The result (file and what the site says about the
video) goes to Laravel's /internal/imports/{record}/done, which starts processing.

Safety: only http(s) addresses whose host resolves to public addresses; only yt-dlp's
site extractors (the generic one, which fetches any page or file, is off unless
IMPORT_ALLOW_GENERIC=true); one video at a time (playlists are refused). Encoders are
told to import only videos they have the rights to.
"""

import ipaddress
import logging
import socket
from collections.abc import Callable
from pathlib import Path
from typing import Any
from urllib.parse import urlsplit

from app.callbacks import post_with_retry
from app.config import Settings, get_settings
from app.pipeline.cancellation import CancellableProgress, PipelineCancelled, is_cancelled
from app.schemas.imports import ImportRequest
from app.storage import relative, resolve

logger = logging.getLogger(__name__)

# yt-dlp's working files, never the finished download.
_TEMPORARY_SUFFIXES = {".part", ".ytdl", ".temp", ".tmp"}


class ImportFailed(Exception):  # noqa: N818 - reported as the import's failure reason
    """Why a download failed, in words the encoder can act on."""


def check_public_url(url: str, resolver: Callable[..., Any] = socket.getaddrinfo) -> None:
    """Refuse addresses that point at this machine or the local network, also when a
    public-looking name resolves there (Laravel's PublicMediaUrl checks the name only)."""
    parts = urlsplit(url)
    if parts.scheme not in ("http", "https") or not parts.hostname:
        raise ImportFailed("Only http and https addresses can be imported.")
    if parts.username or parts.password:
        raise ImportFailed("The address must not contain a user name or password.")
    try:
        addresses = {info[4][0] for info in resolver(parts.hostname, parts.port or None)}
    except (OSError, UnicodeError) as error:
        raise ImportFailed(f"The address {parts.hostname} could not be found.") from error
    for address in addresses:
        if not ipaddress.ip_address(str(address).split("%")[0]).is_global:
            raise ImportFailed(
                "That address points to a local or private network, which cannot be imported."
            )


Hook = Callable[[dict[str, Any]], None]


def options(request: ImportRequest, settings: Settings, hook: Hook) -> dict[str, Any]:
    height = settings.import_max_height
    limit = request.max_duration_seconds

    def too_long(info: dict[str, Any], *, incomplete: bool = False) -> str | None:
        duration = info.get("duration")
        if duration and duration > limit:
            return f"The video is {duration / 60:.0f} minutes long; the limit is {limit / 60:.0f}."
        return None

    output = resolve(request.output_dir) / f"{request.record_file_id}.%(ext)s"
    return {
        "outtmpl": {"default": str(output)},
        "format": f"bv*[height<={height}]+ba/b[height<={height}]/bv*+ba/b",
        # Prefer MP4 and AAC at the size limit, so the master usually plays in browsers.
        "format_sort": [f"res:{height}", "ext:mp4:m4a"],
        "merge_output_format": "mp4",
        "max_filesize": request.max_bytes,
        "match_filter": too_long,
        "allowed_extractors": ["default"]
        if settings.import_allow_generic
        else ["default", "-generic"],
        "noplaylist": True,
        # A playlist that slips past the check below still yields one video at most.
        "playlistend": 1,
        "socket_timeout": settings.import_socket_timeout_seconds,
        "overwrites": True,
        "cachedir": False,
        "quiet": True,
        "noprogress": True,
        "no_warnings": True,
        "progress_hooks": [hook],
    }


def download(
    request: ImportRequest, on_progress: Callable[[int], None], ydl_class: Any = None
) -> dict[str, Any]:
    """Download the video; returns the callback payload of a finished import."""
    settings = get_settings()
    check_public_url(request.url)

    if ydl_class is None:
        try:
            from yt_dlp import YoutubeDL
        except ImportError as error:
            raise ImportFailed(
                "URL import isn't installed in the AI service image (build it with IMPORT=true)."
            ) from error
        ydl_class = YoutubeDL

    output_dir = resolve(request.output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    reached = [0]

    def hook(status: dict[str, Any]) -> None:
        if status.get("status") != "downloading":
            return
        total = status.get("total_bytes") or status.get("total_bytes_estimate")
        if total:
            done = int(status.get("downloaded_bytes") or 0)
            reached[0] = max(reached[0], min(95, int(done * 95 / total)))
        on_progress(reached[0])

    ydl_options = options(request, settings, hook)
    with ydl_class(ydl_options) as ydl:
        # Look first (no download), so playlists and over-long videos are refused early.
        info = ydl.extract_info(request.url, download=False, process=False)
        if info is None:
            raise ImportFailed("No video was found at that address.")
        if info.get("_type") in ("playlist", "multi_video") or info.get("entries"):
            raise ImportFailed("That address is a playlist or channel. Import one video at a time.")
        reason = ydl_options["match_filter"](info)
        if reason:
            raise ImportFailed(reason)
        info = ydl.process_ie_result(info, download=True) or info

    path = _downloaded(output_dir, request.record_file_id)
    if path is None:
        raise ImportFailed(
            f"Nothing was downloaded. The video may be larger than the "
            f"{request.max_bytes // (1024 * 1024)} MB limit, or the site refused the download."
        )
    on_progress(100)
    return {
        "status": "done",
        "path": relative(path),
        "file_size": path.stat().st_size,
        "source": {
            "title": _text(info.get("title"), 500),
            "uploader": _text(info.get("uploader") or info.get("channel"), 255),
            "upload_date": _text(info.get("upload_date"), 8),
            "extractor": _text(info.get("extractor_key") or info.get("extractor"), 64),
            "webpage_url": _text(info.get("webpage_url"), 2048),
            "duration": duration
            if isinstance(duration := info.get("duration"), int | float)
            else None,
        },
    }


def _downloaded(output_dir: Path, file_id: str) -> Path | None:
    """The finished file: named after the file id, not one of yt-dlp's working files,
    and inside the output folder."""
    for candidate in sorted(output_dir.glob(f"{file_id}.*")):
        # yt-dlp's separate video and audio streams are named {id}.f137.mp4 and so on.
        is_stream = ".f" in candidate.stem[len(file_id) :]
        if candidate.suffix.lower() in _TEMPORARY_SUFFIXES or is_stream:
            continue
        if candidate.is_file() and candidate.resolve().is_relative_to(output_dir.resolve()):
            return candidate
    return None


def _clean_up(output_dir: Path, file_id: str) -> None:
    for leftover in output_dir.glob(f"{file_id}*"):
        leftover.unlink(missing_ok=True)


def _text(value: Any, limit: int) -> str | None:
    text = " ".join(str(value).split()) if value else ""
    return text[:limit] or None


def explain(error: Exception) -> str:
    """A yt-dlp error without its "ERROR: [site] id:" prefix, short enough to show."""
    if isinstance(error, ImportFailed):
        return str(error)
    message = str(error).replace("ERROR: ", "").strip()
    if "]" in message[:40]:
        message = message.split("]", 1)[1].strip()
    return f"The video could not be downloaded: {message[:300] or type(error).__name__}"


def run_import(request: ImportRequest, run_id: str, progress: Callable[[int], None]) -> None:
    """Download and report; a cancelled import reports nothing (Laravel already knows)."""
    callback = f"/internal/imports/{request.record_id}/done"
    try:
        payload = download(request, CancellableProgress(run_id, progress))
    except Exception as error:  # noqa: BLE001 - reported to Laravel
        _clean_up(resolve(request.output_dir), request.record_file_id)
        if isinstance(error, PipelineCancelled) or is_cancelled(run_id):
            logger.info("record_id=%s import cancelled", request.record_id)
            return
        logger.warning("record_id=%s import failed: %s", request.record_id, error)
        post_with_retry(callback, {"status": "failed", "error": explain(error)})
        return
    if is_cancelled(run_id):
        _clean_up(resolve(request.output_dir), request.record_file_id)
        return
    post_with_retry(callback, payload)
