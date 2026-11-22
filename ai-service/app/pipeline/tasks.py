"""Celery wiring for the Phase 1 pipeline (PROJECT_SPEC §6).

The stages run as one chain. Each task receives the pipeline context, runs its stage
(unless a dependency failed or was skipped), reports to Laravel, and hands the context on.
A failing stage never breaks the chain: independent stages still run, dependent ones are
reported as `skipped`. Transcription runs on the `gpu` queue (one task at a time).
"""

import logging
from collections.abc import Callable
from typing import Any

import openai
from celery import chain

from app.callbacks import ProgressReporter, report_progress, report_result
from app.pipeline import stages
from app.pipeline.audio import AudioError
from app.pipeline.probe import ProbeError
from app.pipeline.proxy import ProxyError
from app.providers.llm import LlmError
from app.providers.transcription.local import ModelNotReadyError
from app.schemas.pipeline import PipelineContext, ProcessRequest, StageName
from app.storage import UnsafePathError
from app.worker import celery_app

logger = logging.getLogger(__name__)

GPU_QUEUE = "gpu"

StageFunction = Callable[[PipelineContext, Callable[[int], None]], dict[str, Any]]

# Errors whose message is safe and useful to show the encoder as-is.
_EXPLAINED = (
    ProbeError,
    ProxyError,
    AudioError,
    LlmError,
    UnsafePathError,
    FileNotFoundError,
    ModelNotReadyError,
)


def user_message(stage: str, error: Exception) -> str:
    if isinstance(error, _EXPLAINED):
        return str(error)
    if isinstance(error, openai.AuthenticationError):
        return "OpenAI rejected the API key. Check OPENAI_API_KEY in ai-service/.env."
    if isinstance(error, openai.RateLimitError):
        return "OpenAI's rate limit or quota was reached. Retry later."
    if isinstance(error, openai.APIError):
        return f"OpenAI returned an error ({type(error).__name__})."
    return f"The {stage.replace('_', ' ')} step failed unexpectedly ({type(error).__name__})."


def run_stage(
    stage: StageName, context_data: dict[str, Any], work: StageFunction
) -> dict[str, Any]:
    context = PipelineContext.model_validate(context_data)
    record_id = context.request.record_id
    log = logging.LoggerAdapter(logger, {"record_id": record_id, "stage": stage})

    if blocked_by := context.blocked_by(stage):
        report_progress(
            record_id,
            stage,
            "skipped",
            message=f"Skipped because the {blocked_by.replace('_', ' ')} step did not finish.",
        )
        context.skipped.append(stage)
        return context.model_dump()

    report_progress(record_id, stage, "running", 0)
    try:
        result = work(context, ProgressReporter(record_id, stage))
    except stages.SkipStage as skip:
        report_progress(record_id, stage, "skipped", message=str(skip))
        context.skipped.append(stage)
        return context.model_dump()
    except Exception as error:  # noqa: BLE001 - reported to Laravel, chain continues
        log.exception("record_id=%s stage=%s failed", record_id, stage)
        report_progress(record_id, stage, "failed", message=user_message(stage, error))
        context.failed.append(stage)
        return context.model_dump()

    report_result(record_id, stage, result)
    log.info("record_id=%s stage=%s done", record_id, stage)
    return context.model_dump()


@celery_app.task(name="pipeline.checksum", soft_time_limit=2 * 60 * 60)
def checksum_task(context: dict[str, Any]) -> dict[str, Any]:
    return run_stage("checksum", context, stages.run_checksum)


@celery_app.task(name="pipeline.probe", soft_time_limit=10 * 60)
def probe_task(context: dict[str, Any]) -> dict[str, Any]:
    return run_stage("probe", context, stages.run_probe)


@celery_app.task(name="pipeline.extract_audio", soft_time_limit=60 * 60)
def extract_audio_task(context: dict[str, Any]) -> dict[str, Any]:
    return run_stage("extract_audio", context, stages.run_extract_audio)


@celery_app.task(name="pipeline.transcribe", soft_time_limit=4 * 60 * 60)
def transcribe_task(context: dict[str, Any]) -> dict[str, Any]:
    return run_stage("transcribe", context, stages.run_transcribe)


@celery_app.task(name="pipeline.generate_metadata", soft_time_limit=20 * 60)
def generate_metadata_task(context: dict[str, Any]) -> dict[str, Any]:
    return run_stage("generate_metadata", context, stages.run_generate_metadata)


@celery_app.task(name="pipeline.proxy", soft_time_limit=6 * 60 * 60)
def proxy_task(context: dict[str, Any]) -> dict[str, Any]:
    return run_stage("proxy", context, stages.run_proxy)


def start_pipeline(request: ProcessRequest) -> str:
    """Queue the chain; returns the id of the last task (the job id)."""
    context = PipelineContext(request=request).model_dump()
    workflow = chain(
        checksum_task.s(context),
        probe_task.s(),
        extract_audio_task.s(),
        transcribe_task.s().set(queue=GPU_QUEUE),
        generate_metadata_task.s(),
        proxy_task.s(),
    )
    return str(workflow.apply_async().id)
