"""Stage `scenes`: shot changes with PySceneDetect's content detector (PROJECT_SPEC §6).

The result is a list of shots covering the whole video (one shot when there is no cut).
Detection runs in a helper thread so progress can be reported from the frame position.
"""

from collections.abc import Callable
from concurrent.futures import ThreadPoolExecutor
from concurrent.futures import TimeoutError as FutureTimeout
from pathlib import Path

from app.pipeline.visual import VisualError, missing_extra
from app.schemas.visual import Shot

PROGRESS_INTERVAL_SECONDS = 2.0
# Shorter than this (in frames) is not a shot: flashes and dissolves.
MIN_SHOT_FRAMES = 15


def detect(
    path: Path,
    duration: float | None,
    threshold: float,
    on_progress: Callable[[int], None] | None = None,
) -> list[Shot]:
    try:
        from scenedetect import ContentDetector, SceneManager, open_video
    except ImportError as error:
        raise missing_extra(error) from error

    try:
        video = open_video(str(path))
    except Exception as error:  # noqa: BLE001 - every backend failure means the same
        raise VisualError(
            f"The video could not be opened for scene detection ({error})."
        ) from error

    manager = SceneManager()
    manager.auto_downscale = True
    manager.add_detector(ContentDetector(threshold=threshold, min_scene_len=MIN_SHOT_FRAMES))

    pool = ThreadPoolExecutor(max_workers=1, thread_name_prefix="scenedetect")
    try:
        future = pool.submit(manager.detect_scenes, video=video, show_progress=False)
        while True:
            try:
                future.result(timeout=PROGRESS_INTERVAL_SECONDS)
                break
            except FutureTimeout:
                if duration and on_progress:
                    position = video.position.seconds
                    on_progress(min(95, int(position * 100 / duration)))
    except BaseException:
        # A time limit or a crash: stop the detector so the thread doesn't keep reading.
        stop = getattr(manager, "stop", None)
        if callable(stop):
            stop()
        raise
    finally:
        pool.shutdown(wait=False, cancel_futures=True)

    scenes = manager.get_scene_list(start_in_scene=True)
    shots = [
        Shot(start=round(start.seconds, 3), end=round(end.seconds, 3)) for start, end in scenes
    ]
    if not shots and duration:
        shots = [Shot(start=0.0, end=round(duration, 3))]
    return shots


def cap(shots: list[Shot], limit: int) -> list[Shot]:
    """At most `limit` shots: the rest are merged into the last one, so the list still
    covers the whole video."""
    if len(shots) <= limit:
        return shots
    kept = shots[: limit - 1]
    return [*kept, Shot(start=shots[limit - 1].start, end=shots[-1].end)]
