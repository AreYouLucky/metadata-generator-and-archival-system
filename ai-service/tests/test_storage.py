from pathlib import Path

import pytest

from app.config import get_settings
from app.storage import UnsafePathError, relative, resolve


@pytest.fixture
def storage_root(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> Path:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    return tmp_path


def test_relative_paths_resolve_inside_the_storage_root(storage_root: Path) -> None:
    path = resolve("records/01J/master/a.mp4")

    assert path == storage_root.resolve() / "records/01J/master/a.mp4"
    assert relative(path) == "records/01J/master/a.mp4"


@pytest.mark.parametrize(
    "unsafe",
    ["/etc/passwd", "../secrets.env", "records/../../etc/passwd", "records\\..\\x"],
)
def test_paths_that_could_leave_the_storage_root_are_rejected(
    storage_root: Path, unsafe: str
) -> None:
    with pytest.raises(UnsafePathError):
        resolve(unsafe)
