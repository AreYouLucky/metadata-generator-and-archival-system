"""Celery wiring for URL imports (POST /import-url); the work is in app.pipeline.importer."""

from typing import Any

from app.callbacks import ProgressReporter
from app.pipeline import importer
from app.pipeline.cancellation import new_run_id
from app.schemas.imports import ImportRequest
from app.worker import celery_app


@celery_app.task(name="import.url", soft_time_limit=3 * 60 * 60)
def import_task(request_data: dict[str, Any], run_id: str) -> None:
    request = ImportRequest.model_validate(request_data)
    # Progress shows on the record's `import` stage, like a pipeline stage.
    importer.run_import(request, run_id, ProgressReporter(request.record_id, "import"))


def start_import(request: ImportRequest) -> str:
    """Queue the download; returns the run id, which POST /jobs/{id}/cancel stops."""
    run_id = new_run_id()
    import_task.delay(request.model_dump(), run_id)
    return run_id
