from fastapi import FastAPI

from app import __version__
from app.api import health, ping

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
