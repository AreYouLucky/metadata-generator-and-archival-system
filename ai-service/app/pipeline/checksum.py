"""Stage `checksum`: MD5 + SHA-256 in one streaming pass (PROJECT_SPEC §11)."""

import hashlib
from collections.abc import Callable
from pathlib import Path
from typing import TypedDict

CHUNK_BYTES = 8 * 1024 * 1024


class Checksums(TypedDict):
    md5: str
    sha256: str
    file_size: int


def compute(path: Path, on_progress: Callable[[int], None] | None = None) -> Checksums:
    md5 = hashlib.md5(usedforsecurity=False)
    sha256 = hashlib.sha256()
    total = path.stat().st_size
    done = 0
    with path.open("rb") as handle:
        while chunk := handle.read(CHUNK_BYTES):
            md5.update(chunk)
            sha256.update(chunk)
            done += len(chunk)
            if on_progress and total:
                on_progress(int(done * 100 / total))
    return {"md5": md5.hexdigest(), "sha256": sha256.hexdigest(), "file_size": done}
