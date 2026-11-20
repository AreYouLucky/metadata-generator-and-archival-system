FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DEFAULT_TIMEOUT=120 \
    PIP_RETRIES=10

# System packages (ffmpeg/ffprobe, mediainfo, OpenCV libs) are added in the
# phase that first uses them; ffmpeg alone is several hundred MB.

WORKDIR /srv

COPY pyproject.toml ./
COPY app ./app
RUN pip install ".[dev]"

COPY tests ./tests

# Same UID as the Laravel image so both can write to the shared media volume.
RUN useradd --uid 1000 --create-home app \
    && mkdir -p /data/storage \
    && chown app:app /data/storage
USER app

EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
