# Deno runs the JavaScript yt-dlp needs for YouTube (yt-dlp-ejs; 2.3.0 or newer).
ARG DENO_VERSION=2.5.6
FROM denoland/deno:bin-${DENO_VERSION} AS deno

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
# Visual analysis (scenes, frames, OCR, objects) is on by default. EasyOCR and
# Ultralytics need torch: its CPU build is installed first (about 200 MB instead of
# several GB of CUDA libraries); the GPU stays with Whisper. --build-arg VISION=false
# builds without them, and the visual stages then fail with a clear message.
ARG VISION=true
# Speaker identification (pyannote, Phase 6) on the same CPU build of torch, and URL
# import (yt-dlp, Phase 6). --build-arg DIARIZATION=false / IMPORT=false leave them
# out; those stages then fail with a clear message.
ARG DIARIZATION=true
ARG IMPORT=true
ARG TORCH_INDEX_URL=https://download.pytorch.org/whl/cpu

# Dependencies first, against a stub package, so code changes don't
# re-download them. The real code in /srv/app shadows the stub at runtime.
# Ultralytics pulls in the desktop OpenCV (it needs libGL): only the headless
# build is kept.
COPY pyproject.toml ./
RUN mkdir -p app && touch app/__init__.py \
    && extras="dev" \
    && if [ "$LOCAL_WHISPER" = "true" ]; then extras="$extras,local"; fi \
    && if [ "$VISION" = "true" ] || [ "$DIARIZATION" = "true" ]; then \
        pip install torch torchvision torchaudio --index-url "$TORCH_INDEX_URL"; \
    fi \
    && if [ "$VISION" = "true" ]; then extras="$extras,vision"; fi \
    && if [ "$DIARIZATION" = "true" ]; then extras="$extras,diarization"; fi \
    && if [ "$IMPORT" = "true" ]; then extras="$extras,import"; fi \
    && pip install ".[$extras]" \
    && if [ "$VISION" = "true" ]; then \
        version="$(python -c 'import importlib.metadata as m; print(m.version("opencv-python-headless"))')" \
        && pip uninstall -y opencv-python \
        && pip install --force-reinstall --no-deps "opencv-python-headless==$version"; \
    fi \
    && rm -rf app build *.egg-info
COPY --from=deno /deno /usr/local/bin/deno
COPY app ./app

# CTranslate2 loads cuBLAS and cuDNN from the pip wheels (faster-whisper README).
# OCR and object-detection models are downloaded into /srv/models (a volume) on first
# use; Ultralytics never installs packages at run time.
ENV LD_LIBRARY_PATH=/usr/local/lib/python3.12/site-packages/nvidia/cublas/lib:/usr/local/lib/python3.12/site-packages/nvidia/cudnn/lib \
    HF_HOME=/srv/models \
    YOLO_CONFIG_DIR=/srv/models/ultralytics \
    YOLO_AUTOINSTALL=false

COPY tests ./tests

# Same UID as the Laravel image so both can write to the shared media volume.
RUN useradd --uid 1000 --create-home app \
    && mkdir -p /data/storage /srv/models \
    && chown app:app /data/storage /srv/models
USER app

EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
