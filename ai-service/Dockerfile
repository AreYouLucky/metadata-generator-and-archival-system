FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DEFAULT_TIMEOUT=120 \
    PIP_RETRIES=10

# ffmpeg/ffprobe for probing and audio extraction, libmediainfo for the
# second opinion on scan type, bit depth and chroma. Retries because the
# download is large and the dev connection is slow.
RUN apt-get -o Acquire::Retries=10 update \
    && apt-get -o Acquire::Retries=10 install -y --no-install-recommends ffmpeg libmediainfo0v5 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv

# Local transcription (faster-whisper + cuBLAS/cuDNN for CUDA 12) is on by
# default. Build with --build-arg LOCAL_WHISPER=false for an image without it.
ARG LOCAL_WHISPER=true

# Dependencies first, against a stub package, so code changes don't
# re-download them. The real code in /srv/app shadows the stub at runtime.
COPY pyproject.toml ./
RUN mkdir -p app && touch app/__init__.py \
    && if [ "$LOCAL_WHISPER" = "true" ]; then pip install ".[dev,local]"; else pip install ".[dev]"; fi \
    && rm -rf app build *.egg-info
COPY app ./app

# CTranslate2 loads cuBLAS and cuDNN from the pip wheels (faster-whisper README).
ENV LD_LIBRARY_PATH=/usr/local/lib/python3.12/site-packages/nvidia/cublas/lib:/usr/local/lib/python3.12/site-packages/nvidia/cudnn/lib \
    HF_HOME=/srv/models

COPY tests ./tests

# Same UID as the Laravel image so both can write to the shared media volume.
RUN useradd --uid 1000 --create-home app \
    && mkdir -p /data/storage /srv/models \
    && chown app:app /data/storage /srv/models
USER app

EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
