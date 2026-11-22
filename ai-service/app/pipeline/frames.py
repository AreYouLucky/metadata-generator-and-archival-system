"""Stage `frames`: key frames at scene starts plus one every FRAME_INTERVAL_SEC (PROJECT_SPEC §6).

Frames are JPEGs at most FRAME_MAX_HEIGHT tall, with square pixels and deinterlaced, in
`records/{id}/frames/`. Each frame is compared with the one before it: a frame that looks
the same (a slide held on screen) is marked as a duplicate, and the later stages copy the
earlier frame's results instead of reading or paying for it again.
"""

import shutil
import subprocess  # noqa: S404 - fixed argv, no shell
from collections.abc import Callable
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass
from pathlib import Path

from app.pipeline.visual import VisualError, missing_extra
from app.schemas.visual import Shot

FRAME_TIMEOUT_SECONDS = 120
PARALLEL_EXTRACTIONS = 4
# How far into a shot its frame is taken, so a dissolve or fade isn't what is kept.
SHOT_OFFSET_SECONDS = 0.5
# Frames closer than this are the same moment.
MIN_GAP_SECONDS = 0.5

# Duplicate test on 160x90 greyscale copies: a frame is "the same" when fewer than
# 0.2% of the cells changed by more than 24 levels. A new line of text on a slide
# changes far more cells than that; compression noise changes almost none.
COMPARE_SIZE = (160, 90)
CHANGED_LEVEL = 24
DUPLICATE_MAX_CHANGED = 0.002


@dataclass(frozen=True)
class PlannedFrame:
    time_sec: float
    is_scene_start: bool


def plan(
    duration: float | None, shots: list[Shot], interval: float, max_frames: int
) -> list[PlannedFrame]:
    """Which moments to take: every shot's start (a little into the shot) and one frame
    per interval in between. For long videos the interval grows so the interval frames
    alone fit in `max_frames`; when there are more shots than that, scene starts are
    spread evenly over the video."""
    if not duration or duration <= 0:
        return [PlannedFrame(0.0, True)]

    last = max(0.0, duration - 0.1)
    interval = max(interval, duration / max_frames)

    starts: list[PlannedFrame] = []
    for shot in shots or [Shot(start=0.0, end=duration)]:
        offset = min(SHOT_OFFSET_SECONDS, max(0.0, (shot.end - shot.start) / 2))
        time = min(last, shot.start + offset)
        if not starts or time - starts[-1].time_sec >= MIN_GAP_SECONDS:
            starts.append(PlannedFrame(round(time, 3), True))

    between: list[PlannedFrame] = []
    time = interval
    while time < last:
        # Not right next to a scene start: that frame already shows this moment.
        if all(abs(time - start.time_sec) >= interval / 2 for start in starts):
            between.append(PlannedFrame(round(time, 3), False))
        time += interval

    if len(starts) >= max_frames:
        chosen = _spread(starts, max_frames)
    else:
        chosen = starts + _spread(between, max_frames - len(starts))
    return sorted(chosen, key=lambda frame: frame.time_sec)


def _spread(items: list[PlannedFrame], count: int) -> list[PlannedFrame]:
    """`count` items spread evenly, keeping the first and the last."""
    if count <= 0:
        return []
    if len(items) <= count:
        return list(items)
    if count == 1:
        return [items[0]]
    step = (len(items) - 1) / (count - 1)
    return [items[round(index * step)] for index in range(count)]


def command(source: Path, target: Path, time_sec: float, max_height: int) -> list[str]:
    filters = ",".join(
        [
            # Only frames flagged as interlaced are touched.
            "yadif=deint=interlaced",
            # Square pixels (anamorphic DV and SD masters), then no taller than max_height.
            "scale=trunc(ih*dar/2)*2:ih",
            "setsar=1",
            f"scale=-2:'min({max_height},ih)'",
        ]
    )
    return [
        "ffmpeg",
        "-nostdin",
        "-hide_banner",
        "-loglevel",
        "error",
        "-y",
        # Before -i: a fast seek to the keyframe, then decoded up to the exact time.
        "-ss",
        f"{time_sec:.3f}",
        "-i",
        str(source),
        "-map",
        "0:v:0",
        "-frames:v",
        "1",
        "-vf",
        filters,
        "-q:v",
        "3",
        # One still image, not a numbered sequence.
        "-update",
        "1",
        str(target),
    ]


def extract_frame(source: Path, target: Path, time_sec: float, max_height: int) -> Path:
    """One frame as a JPEG. Written next to the target first, so a failed extraction
    never leaves half a file behind."""
    target.parent.mkdir(parents=True, exist_ok=True)
    partial = target.with_name(f".{target.stem}.part.jpg")
    try:
        completed = subprocess.run(  # noqa: S603 - fixed argv, paths confined to storage
            command(source, partial, time_sec, max_height),
            capture_output=True,
            timeout=FRAME_TIMEOUT_SECONDS,
            check=False,
        )
    except subprocess.TimeoutExpired as error:
        partial.unlink(missing_ok=True)
        raise VisualError(f"Taking the frame at {time_sec:.1f} s timed out.") from error
    if completed.returncode != 0 or not partial.is_file() or partial.stat().st_size == 0:
        partial.unlink(missing_ok=True)
        message = completed.stderr.decode(errors="replace").strip()
        raise VisualError(
            f"ffmpeg could not take the frame at {time_sec:.1f} s: {message[:300] or 'no image'}"
        )
    partial.replace(target)
    return target


def extract_all(
    source: Path,
    directory: Path,
    planned: list[PlannedFrame],
    max_height: int,
    on_progress: Callable[[int], None] | None = None,
) -> list[tuple[PlannedFrame, Path]]:
    """Every planned frame, a few at a time. The folder is emptied first (a re-run
    replaces the frames). Frames past the real end of the stream are left out."""
    shutil.rmtree(directory, ignore_errors=True)
    directory.mkdir(parents=True, exist_ok=True)

    done: dict[int, Path] = {}
    errors: list[VisualError] = []
    with ThreadPoolExecutor(max_workers=PARALLEL_EXTRACTIONS) as pool:
        futures = {
            pool.submit(
                extract_frame,
                source,
                directory / f"{index:03d}_{round(frame.time_sec * 1000)}.jpg",
                frame.time_sec,
                max_height,
            ): index
            for index, frame in enumerate(planned)
        }
        for finished, future in enumerate(as_completed(futures), start=1):
            try:
                done[futures[future]] = future.result()
            except VisualError as error:
                errors.append(error)
            if on_progress:
                on_progress(int(finished * 80 / len(planned)))

    if not done:
        raise errors[0] if errors else VisualError("No frame could be taken from the video.")
    return [(planned[index], done[index]) for index in sorted(done)]


@dataclass(frozen=True)
class FrameLook:
    """Cheap measures of a frame: a small greyscale copy, brightness and contrast."""

    pixels: bytes
    brightness: float
    contrast: float


def look(path: Path) -> FrameLook:
    try:
        from PIL import Image, ImageStat
    except ImportError as error:
        raise missing_extra(error) from error

    with Image.open(path) as image:
        grey = image.convert("L")
        stats = ImageStat.Stat(grey)
        small = grey.resize(COMPARE_SIZE, Image.Resampling.BOX)
        return FrameLook(small.tobytes(), stats.mean[0], stats.stddev[0])


def is_duplicate(first: FrameLook, second: FrameLook) -> bool:
    changed = sum(
        1 for a, b in zip(first.pixels, second.pixels, strict=True) if abs(a - b) > CHANGED_LEVEL
    )
    return changed / len(first.pixels) < DUPLICATE_MAX_CHANGED


def duplicates(looks: list[FrameLook]) -> list[int | None]:
    """For each frame, the index of the earlier frame it repeats (compared with the
    frame just before it, so a slide held for minutes points at its first frame)."""
    result: list[int | None] = []
    for index, current in enumerate(looks):
        if index > 0 and is_duplicate(looks[index - 1], current):
            previous = result[index - 1]
            result.append(previous if previous is not None else index - 1)
        else:
            result.append(None)
    return result


def suggested_thumbnail(
    looks: list[FrameLook], times: list[float], repeats: list[int | None]
) -> int | None:
    """A default thumbnail: the first distinct frame after the opening second that is
    neither near black nor near white and has some contrast (often a title card), else
    the frame with the most contrast."""
    if not looks:
        return None
    for index, frame in enumerate(looks):
        if (
            repeats[index] is None
            and times[index] >= 1.0
            and 40 <= frame.brightness <= 215
            and frame.contrast >= 30
        ):
            return index
    return max(range(len(looks)), key=lambda index: looks[index].contrast)
