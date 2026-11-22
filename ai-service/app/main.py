import logging

from fastapi import FastAPI

from app import __version__
from app.api import frames, generate, health, jobs, ping

# Structured enough to grep: every pipeline log line carries record_id=... (PROJECT_SPEC §14).
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")

app = FastAPI(
    title="MGAS AI service",
    version=__version__,
    # Internal service: no public docs in production builds.
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)

app.include_router(health.router)
app.include_router(ping.router)
app.include_router(jobs.router)
app.include_router(generate.router)
app.include_router(frames.router)
