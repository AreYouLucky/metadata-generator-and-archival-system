"""Visual analysis logic that needs no model: which frames are taken, which repeat,
how OCR words become lines, how detections are summarised, and how the stages hand
their results on (PROJECT_SPEC §6, stages 7-11)."""

from pathlib import Path
from typing import Any

import pytest

from app.pipeline import frames, objects, ocr, scenes, stages, tasks
from app.pipeline.stages import SkipStage
from app.schemas.common import PresetRules, TranscriptSegment
from app.schemas.pipeline import JobOptions, PipelineContext, ProcessRequest
from app.schemas.visual import FrameInfo, Shot

RECORD_ID = "01JDZ8Q7X3V5K9M2N4P6R8T0W1"


def context(**options: Any) -> PipelineContext:
    return PipelineContext(
        request=ProcessRequest(
            record_id=RECORD_ID,
            record_file_id=RECORD_ID,
            video_path="records/x/master/y.mp4",
            options=JobOptions(
                preset=PresetRules(name="generic", label="Generic", description_max=2000),
                **options,
            ),
        )
    )


# --- frames.plan -------------------------------------------------------------------


def test_frames_are_taken_at_scene_starts_and_every_interval_in_between() -> None:
    planned = frames.plan(60.0, [Shot(start=0, end=30), Shot(start=30, end=60)], 10, 60)

    assert [(f.time_sec, f.is_scene_start) for f in planned] == [
        (0.5, True),
        (10.0, False),
        (20.0, False),
        (30.5, True),
        (40.0, False),
        (50.0, False),
    ]


def test_the_interval_grows_so_a_long_video_stays_within_max_frames() -> None:
    planned = frames.plan(3600.0, [Shot(start=0, end=3600)], 10, 20)

    assert len(planned) <= 20
    assert planned[-1].time_sec > 3000  # the end of the video is still covered


def test_with_more_shots_than_frames_the_scene_starts_are_spread_evenly() -> None:
    shots = [Shot(start=index * 2.0, end=index * 2.0 + 2) for index in range(100)]

    planned = frames.plan(200.0, shots, 10, 10)

    assert len(planned) == 10
    assert all(frame.is_scene_start for frame in planned)
    assert planned[0].time_sec == 0.5
    assert planned[-1].time_sec == 198.5


def test_without_a_duration_one_frame_is_taken_at_the_start() -> None:
    assert frames.plan(None, [], 10, 60) == [frames.PlannedFrame(0.0, True)]


# --- duplicates and the default thumbnail -------------------------------------------


def look(value: int, changed: int = 0, brightness: float = 120, contrast: float = 50) -> Any:
    size = frames.COMPARE_SIZE[0] * frames.COMPARE_SIZE[1]
    pixels = bytes([value] * (size - changed) + [255] * changed)
    return frames.FrameLook(pixels, brightness, contrast)


def test_a_slide_held_on_screen_points_at_its_first_frame() -> None:
    looks = [look(10), look(10), look(10, changed=5), look(200)]

    assert frames.duplicates(looks) == [None, 0, 0, None]


def test_a_new_line_of_text_is_not_a_duplicate() -> None:
    # A line of text changes a few percent of the small copy, far over 0.2%.
    assert not frames.is_duplicate(look(10), look(10, changed=300))


def test_the_default_thumbnail_skips_the_opening_dark_and_repeated_frames() -> None:
    looks = [
        look(10, brightness=120),  # at 0.5 s: too early
        look(11, brightness=10),  # black
        look(12, brightness=120, contrast=5),  # flat
        look(13, brightness=120),  # a repeat (below)
        look(14, brightness=130),
    ]
    times = [0.5, 5.0, 10.0, 15.0, 20.0]

    assert frames.suggested_thumbnail(looks, times, [None, None, None, 2, None]) == 4


# --- OCR lines, objects, shots --------------------------------------------------------


def box(x: float, y: float, width: float = 40, height: float = 20) -> list[list[float]]:
    return [[x, y], [x + width, y], [x + width, y + height], [x, y + height]]


def test_ocr_words_are_read_as_lines_left_to_right_and_weak_ones_dropped() -> None:
    results = [
        (box(200, 102), "Fair", 0.9),
        (box(10, 100), "Regional", 0.95),
        (box(100, 98), "S&T", 0.8),
        (box(10, 200), "Cebu   City", 0.7),
        (box(300, 300), "~~", 0.1),
    ]

    assert ocr.lines_from(results, 0.4) == "Regional S&T Fair\nCebu City"


def test_each_object_label_keeps_its_highest_confidence() -> None:
    found = objects.strongest([("person", 0.5), ("chair", 0.9), ("person", 0.8)])

    assert [(item.label, item.confidence) for item in found] == [("chair", 0.9), ("person", 0.8)]


def test_shots_over_the_limit_are_merged_into_the_last() -> None:
    shots = [Shot(start=index, end=index + 1) for index in range(10)]

    capped = scenes.cap(shots, 4)

    assert len(capped) == 4
    assert capped[-1] == Shot(start=3, end=10)


# --- options and branches ----------------------------------------------------------------


def test_visual_stages_are_switched_off_by_the_upload_options() -> None:
    assert context().disabled_reason("scenes") == "Visual analysis was turned off for this video."
    on = context(visual_analysis=True, ocr=False, object_detection=True)
    assert on.disabled_reason("frames") is None
    assert on.disabled_reason("ocr") == "On-screen text recognition was turned off for this video."
    assert on.disabled_reason("objects") is None


def test_a_switched_off_stage_is_skipped_with_its_reason(monkeypatch: pytest.MonkeyPatch) -> None:
    reported: list[tuple[str, str, str | None]] = []
    monkeypatch.setattr(
        tasks,
        "report_progress",
        lambda record_id, stage, status, progress=0, message=None, retry_on_error=True: (
            reported.append((stage, status, message))
        ),
    )

    out = tasks.run_stage("ocr", context(visual_analysis=True).model_dump(), lambda c, p: {})

    assert reported == [
        ("ocr", "skipped", "On-screen text recognition was turned off for this video.")
    ]
    assert out["skipped"] == ["ocr"]


def test_the_branches_are_joined_with_each_ones_results() -> None:
    start = context(visual_analysis=True)
    start.failed = ["checksum"]
    audio = start.model_copy(deep=True)
    audio.segments = [TranscriptSegment(start=0, end=1, text="Hello")]
    audio.language = "en"
    visual = start.model_copy(deep=True)
    visual.frames = [FrameInfo(time_sec=0.5, path="records/x/frames/000_500.jpg")]
    visual.failed.append("ocr")
    visual.skipped.append("objects")

    joined = PipelineContext.join([audio, visual])

    assert joined.language == "en"
    assert len(joined.segments) == 1
    assert len(joined.frames) == 1
    assert joined.failed == ["checksum", "ocr"]
    assert joined.skipped == ["objects"]


def test_the_workflow_runs_the_audio_and_visual_branches_side_by_side() -> None:
    workflow = tasks.workflow(context().request)

    # Celery folds what follows the branches into the chord's body.
    assert [task.task for task in workflow.tasks] == [
        "pipeline.checksum",
        "pipeline.probe",
        "celery.chord",
    ]
    chord = workflow.tasks[2]
    assert [task.task for task in chord.body.tasks] == [
        "pipeline.join",
        "pipeline.generate_metadata",
        "pipeline.proxy",
    ]
    branches = getattr(chord.tasks, "tasks", chord.tasks)
    assert [task.task for task in branches[0].tasks] == [
        "pipeline.extract_audio",
        "pipeline.transcribe",
    ]
    assert [task.task for task in branches[1].tasks] == [
        "pipeline.scenes",
        "pipeline.frames",
        "pipeline.vision",
        "pipeline.ocr",
        "pipeline.objects",
    ]


# --- stage hand-over -----------------------------------------------------------------------


def with_frames(**options: Any) -> PipelineContext:
    ctx = context(visual_analysis=True, ocr=True, object_detection=True, **options)
    ctx.frames = [
        FrameInfo(time_sec=0.5, path="records/x/frames/000.jpg", is_scene_start=True),
        FrameInfo(time_sec=10.0, path="records/x/frames/001.jpg", duplicate_of=0),
        FrameInfo(time_sec=20.5, path="records/x/frames/002.jpg", is_scene_start=True),
    ]
    return ctx


def test_ocr_reads_each_distinct_frame_once_and_copies_it_to_repeats(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Path
) -> None:
    monkeypatch.setattr(stages, "resolve", lambda path: tmp_path / Path(path).name)
    read: list[str] = []
    monkeypatch.setattr(
        stages.ocr, "read", lambda path: read.append(path.name) or f"text of {path.name}"
    )
    ctx = with_frames()

    result = stages.run_ocr(ctx, lambda value: None)

    assert read == ["000.jpg", "002.jpg"]
    assert [frame["text"] for frame in result["frames"]] == [
        "text of 000.jpg",
        "text of 000.jpg",
        "text of 002.jpg",
    ]
    assert ctx.frames[1].ocr_text == "text of 000.jpg"


def test_objects_and_face_counts_are_copied_to_repeats(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Path
) -> None:
    monkeypatch.setattr(stages, "resolve", lambda path: tmp_path / Path(path).name)
    detected = [
        (objects.strongest([("person", 0.9)]), 2),
        (objects.strongest([("laptop", 0.7)]), 0),
    ]
    monkeypatch.setattr(stages.objects, "detect", lambda paths: detected[: len(paths)])

    result = stages.run_objects(with_frames(), lambda value: None)

    assert [frame["face_count"] for frame in result["frames"]] == [2, 2, 0]
    assert result["frames"][1]["objects"] == [{"label": "person", "confidence": 0.9}]


def test_metadata_is_written_from_the_frames_when_nothing_is_said(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    seen: dict[str, Any] = {}

    class Generator:
        def generate(self, segments: Any, preset: Any, **kwargs: Any) -> Any:
            seen.update(kwargs, segments=segments)
            raise SkipStage("stop here")

    monkeypatch.setattr(stages, "MetadataGenerator", Generator)
    ctx = with_frames()
    ctx.frames[0].ocr_text = "Rice production 2026"
    ctx.skipped.append("transcribe")

    with pytest.raises(SkipStage, match="stop here"):
        stages.run_generate_metadata(ctx, lambda value: None)

    assert seen["segments"] == []
    # The repeat of frame 0 is not sent twice.
    assert [frame.time_sec for frame in seen["visual"]] == [0.5]


def test_metadata_is_skipped_when_nothing_is_said_or_shown() -> None:
    ctx = context(visual_analysis=True)
    ctx.skipped.append("transcribe")

    with pytest.raises(SkipStage, match="transcribe step did not finish"):
        stages.run_generate_metadata(ctx, lambda value: None)

    with pytest.raises(SkipStage, match="Nothing was said or shown"):
        stages.run_generate_metadata(context(), lambda value: None)
