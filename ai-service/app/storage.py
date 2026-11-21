"""Paths inside the shared media volume. Laravel sends paths relative to STORAGE_ROOT;
anything that would escape the root is rejected."""

from pathlib import Path, PurePosixPath

from app.config import Settings, get_settings


class UnsafePathError(ValueError):
    pass


def resolve(relative_path: str, settings: Settings | None = None) -> Path:
    settings = settings or get_settings()
    root = Path(settings.storage_root).resolve()
    candidate = PurePosixPath(relative_path)
    if candidate.is_absolute() or ".." in candidate.parts or "\\" in relative_path:
        raise UnsafePathError(f"Path must be relative to the storage root: {relative_path!r}")
    resolved = (root / candidate).resolve()
    if not resolved.is_relative_to(root):
        raise UnsafePathError(f"Path escapes the storage root: {relative_path!r}")
    return resolved


def relative(path: Path, settings: Settings | None = None) -> str:
    settings = settings or get_settings()
    return path.resolve().relative_to(Path(settings.storage_root).resolve()).as_posix()


def work_dir(record_id: str, settings: Settings | None = None) -> Path:
    """Temporary files of one record (extracted audio, chunks). Safe to delete later."""
    path = resolve(f"records/{record_id}/work", settings)
    path.mkdir(parents=True, exist_ok=True)
    return path
