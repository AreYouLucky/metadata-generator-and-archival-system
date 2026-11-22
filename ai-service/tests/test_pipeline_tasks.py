"""Stage orchestration: what each stage reports to Laravel, and how failures propagate."""

from typing import Any

import pytest

from app.pipeline import tasks
from app.pipeline.probe import ProbeError
from app.pipeline.stages import SkipStage
from app.schemas.common import PresetRules
from app.schemas.pipeline import JobOptions, PipelineContext, ProcessRequest

RECORD_ID = "01JDZ8Q7X3V5K9M2N4P6R8T0W1"


@pytest.fixture
def reported(monkeypatch: pytest.MonkeyPatch) -> list[tuple[str, ...]]:
    calls: list[tuple[str, ...]] = []

    def progress(
        record_id: str,
        stage: str,
        status: str,
        progress: int = 0,
        message: str | None = None,
        retry_on_error: bool = True,
    ) -> None:
        calls.append(("progress", stage, status, message or ""))

    def result(record_id: str, stage: str, payload: dict[str, Any]) -> None:
        calls.append(("result", stage))

    monkeypatch.setattr(tasks, "report_progress", progress)
    monkeypatch.setattr(tasks, "report_result", result)
    monkeypatch.setattr(tasks, "ProgressReporter", lambda record_id, stage: lambda value: None)
    return calls


def context(**changes: Any) -> dict[str, Any]:
    request = ProcessRequest(
        record_id=RECORD_ID,
        record_file_id=RECORD_ID,
        video_path="records/x/master/y.mp4",
        options=JobOptions(
            preset=PresetRules(name="generic", label="Generic", description_max=2000)
        ),
    )
    return PipelineContext(request=request, **changes).model_dump()


def test_a_successful_stage_reports_running_then_its_result(
    reported: list[tuple[str, ...]],
) -> None:
    tasks.run_stage("probe", context(), lambda ctx, progress: {"duration": 3.0})

    assert reported == [("progress", "probe", "running", ""), ("result", "probe")]


def test_a_stage_whose_dependency_failed_is_skipped_and_the_chain_continues(
    reported: list[tuple[str, ...]],
) -> None:
    def must_not_run(ctx: PipelineContext, progress: Any) -> dict[str, Any]:
        raise AssertionError("ran")

    out = tasks.run_stage("transcribe", context(failed=["extract_audio"]), must_not_run)

    assert reported == [
        (
            "progress",
            "transcribe",
            "skipped",
            "Skipped because the extract audio step did not finish.",
        )
    ]
    assert out["skipped"] == ["transcribe"]


def test_an_explained_error_is_reported_as_failed_with_its_message(
    reported: list[tuple[str, ...]],
) -> None:
    def too_long(ctx: PipelineContext, progress: Any) -> dict[str, Any]:
        raise ProbeError("The video is 200 minutes long; the limit is 180 minutes.")

    out = tasks.run_stage("probe", context(), too_long)

    assert reported[-1] == (
        "progress",
        "probe",
        "failed",
        "The video is 200 minutes long; the limit is 180 minutes.",
    )
    assert out["failed"] == ["probe"]


def test_an_unexpected_error_is_reported_without_internal_details(
    reported: list[tuple[str, ...]],
) -> None:
    def crash(ctx: PipelineContext, progress: Any) -> dict[str, Any]:
        raise KeyError("/data/storage/secret/path")

    tasks.run_stage("extract_audio", context(), crash)

    assert reported[-1] == (
        "progress",
        "extract_audio",
        "failed",
        "The extract audio step failed unexpectedly (KeyError).",
    )


def test_a_stage_with_nothing_to_do_is_skipped_not_failed(reported: list[tuple[str, ...]]) -> None:
    def silent(ctx: PipelineContext, progress: Any) -> dict[str, Any]:
        raise SkipStage("Nothing was said in the video, so there is nothing to describe.")

    out = tasks.run_stage("generate_metadata", context(), silent)

    assert reported[-1][2] == "skipped"
    assert out["skipped"] == ["generate_metadata"]
    assert out["failed"] == []


def test_a_stage_of_a_cancelled_run_neither_runs_nor_reports(
    reported: list[tuple[str, ...]],
) -> None:
    from app.pipeline import cancellation

    cancellation.cancel("cancelled-run")

    def must_not_run(ctx: PipelineContext, progress: Any) -> dict[str, Any]:
        raise AssertionError("ran")

    tasks.run_stage("probe", context(run_id="cancelled-run"), must_not_run)

    assert reported == []


def test_a_run_cancelled_while_a_stage_works_drops_the_result(
    reported: list[tuple[str, ...]],
) -> None:
    from app.pipeline import cancellation

    def cancelled_midway(ctx: PipelineContext, progress: Any) -> dict[str, Any]:
        cancellation.cancel("midway-run")
        return {"duration": 3.0}

    tasks.run_stage("probe", context(run_id="midway-run"), cancelled_midway)

    assert reported == [("progress", "probe", "running", "")]


def test_progress_stops_a_long_stage_once_its_run_is_cancelled() -> None:
    from app.pipeline import cancellation

    ticks: list[int] = []
    progress = cancellation.CancellableProgress("long-run", ticks.append, interval=0)
    progress(10)
    cancellation.cancel("long-run")

    with pytest.raises(cancellation.PipelineCancelled):
        progress(20)
    assert ticks == [10]
