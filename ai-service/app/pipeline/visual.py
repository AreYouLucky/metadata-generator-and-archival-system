"""Shared pieces of the visual stages (scenes, frames, vision, ocr, objects).

The heavy libraries (OpenCV, PySceneDetect, EasyOCR, Ultralytics) come from the optional
`vision` extra and are imported inside the functions that use them, so the API process
and a worker built without them still start.
"""


class VisualError(RuntimeError):
    """A visual stage failed for a reason the encoder can be told as-is."""


def missing_extra(error: ImportError) -> VisualError:
    return VisualError(
        "Visual analysis needs the AI service image built with its vision libraries "
        f"(VISION=true). Missing: {error.name or 'a vision library'}."
    )
