"""Stage `objects`: object labels per frame (Ultralytics YOLO) and a face count (OpenCV).

Faces are only counted, with OpenCV's bundled frontal-face detector: nothing here
recognises or matches anyone (PROJECT_SPEC §14). Ultralytics' usage analytics are
switched off before the model loads. Both run on the CPU (DECISIONS.md, Phase 5).
Note: Ultralytics is AGPL-3.0 (PROJECT_SPEC §4); check before commercial use.
"""

import importlib
import os
import threading
from collections import defaultdict
from collections.abc import Iterable
from pathlib import Path
from typing import Any

from app.config import Settings, get_settings
from app.pipeline.visual import VisualError, missing_extra
from app.schemas.visual import DetectedObject

MAX_LABELS = 10
BATCH_SIZE = 16

_model: Any = None
_faces: Any = None
_lock = threading.Lock()


def _get_model(settings: Settings) -> Any:
    global _model
    with _lock:
        if _model is None:
            directory = Path(settings.models_dir) / "ultralytics"
            os.environ.setdefault("YOLO_CONFIG_DIR", str(directory))
            # Never pip-install anything at run time.
            os.environ.setdefault("YOLO_AUTOINSTALL", "false")
            try:
                ultralytics = _library("ultralytics")
            except ImportError as error:
                raise missing_extra(error) from error
            settings_store = getattr(ultralytics, "settings", None)
            if settings_store is not None:
                settings_store.update({"sync": False})
            directory.mkdir(parents=True, exist_ok=True)
            try:
                _model = ultralytics.YOLO(str(directory / settings.object_model))
            except Exception as error:  # noqa: BLE001 - download or weights errors
                raise VisualError(
                    f"The object detection model could not be loaded ({type(error).__name__}). "
                    "The first run downloads it, so the AI service needs internet access once."
                ) from error
        return _model


def _get_face_detector() -> Any:
    global _faces
    with _lock:
        if _faces is None:
            try:
                cv2 = _library("cv2")
            except ImportError as error:
                raise missing_extra(error) from error
            _faces = cv2.CascadeClassifier(
                cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
            )
        return _faces


def _library(name: str) -> Any:
    """An optional library as an untyped module (their stubs differ between versions)."""
    return importlib.import_module(name)


def detect(
    paths: list[Path], settings: Settings | None = None
) -> list[tuple[list[DetectedObject], int]]:
    """Objects and the number of faces for each frame, in order."""
    settings = settings or get_settings()
    model = _get_model(settings)
    found: list[list[DetectedObject]] = []
    for start in range(0, len(paths), BATCH_SIZE):
        batch = [str(path) for path in paths[start : start + BATCH_SIZE]]
        for result in model.predict(
            batch, conf=settings.object_min_confidence, device="cpu", verbose=False
        ):
            names = result.names
            boxes = result.boxes
            labels = [str(names[int(index)]) for index in boxes.cls.tolist()]
            found.append(strongest(zip(labels, boxes.conf.tolist(), strict=True)))
    return [(objects, count_faces(path)) for objects, path in zip(found, paths, strict=True)]


def strongest(detections: Iterable[tuple[str, float]]) -> list[DetectedObject]:
    """One entry per label with its highest confidence, most confident first."""
    best: dict[str, float] = defaultdict(float)
    for label, confidence in detections:
        best[label] = max(best[label], float(confidence))
    ranked = sorted(best.items(), key=lambda item: item[1], reverse=True)[:MAX_LABELS]
    return [
        DetectedObject(label=label, confidence=round(min(confidence, 1.0), 3))
        for label, confidence in ranked
    ]


def count_faces(path: Path) -> int:
    cv2 = _library("cv2")
    detector = _get_face_detector()
    image = cv2.imread(str(path))
    if image is None:
        return 0
    grey = cv2.equalizeHist(cv2.cvtColor(image, cv2.COLOR_BGR2GRAY))
    smallest = max(24, grey.shape[0] // 25)
    faces = detector.detectMultiScale(
        grey, scaleFactor=1.1, minNeighbors=6, minSize=(smallest, smallest)
    )
    return int(len(faces))
