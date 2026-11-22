"""URL import: the address check, the download (yt-dlp mocked) and what Laravel hears."""

import json
from pathlib import Path
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.api import jobs
from app.config import get_settings
from app.pipeline import importer
from app.pipeline.importer import ImportFailed, check_public_url
from app.schemas.imports import ImportRequest
from app.security import signed_headers
from tests.conftest import SECRET

RECORD_ID = "01m7z0cwed0mv35vf14kpjeb1d"
FILE_ID = "01m7z0cwed0mv35vf14kpjeb2e"


def resolver(*addresses: str) -> Any:
    return lambda host, port: [(2, 1, 6, "", (address, 443)) for address in addresses]


@pytest.mark.parametrize(
    "address", ["127.0.0.1", "10.0.0.5", "192.168.1.2", "169.254.169.254", "::1"]
)
def test_names_that_resolve_inside_the_network_are_refused(address: str) -> None:
    with pytest.raises(ImportFailed, match="local or private"):
        check_public_url("https://videos.example.com/watch?v=1", resolver(address))


def test_a_public_address_is_accepted() -> None:
    check_public_url("https://videos.example.com/watch?v=1", resolver("93.184.216.34"))


def test_credentials_in_the_address_are_refused() -> None:
    with pytest.raises(ImportFailed, match="user name"):
        check_public_url("https://me:pw@videos.example.com/x", resolver("93.184.216.34"))


@pytest.fixture
def storage(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> Path:
    monkeypatch.setenv("STORAGE_ROOT", str(tmp_path))
    get_settings.cache_clear()
    monkeypatch.setattr(importer, "check_public_url", lambda url: None)
    return tmp_path


def request() -> ImportRequest:
    return ImportRequest(
        record_id=RECORD_ID,
        record_file_id=FILE_ID,
        url="https://videos.example.com/watch?v=1",
        output_dir=f"records/{RECORD_ID}/master",
        max_bytes=10_000_000,
        max_duration_seconds=600,
    )


def fake_ydl(info: dict[str, Any], write: bool = True) -> Any:
    class FakeYoutubeDL:
        options: dict[str, Any] = {}

        def __init__(self, options: dict[str, Any]) -> None:
            FakeYoutubeDL.options = options

        def __enter__(self) -> "FakeYoutubeDL":
            return self

        def __exit__(self, *_: object) -> None:
            return None

        def extract_info(self, url: str, download: bool, process: bool) -> dict[str, Any]:
            return info

        def process_ie_result(self, result: dict[str, Any], download: bool) -> dict[str, Any]:
            target = Path(self.options["outtmpl"]["default"].replace("%(ext)s", "mp4"))
            for hook in self.options["progress_hooks"]:
                hook({"status": "downloading", "downloaded_bytes": 50, "total_bytes": 100})
            if write:
                target.write_bytes(b"video")
                # yt-dlp's separate stream file, which isn't the result.
                target.with_name(f"{FILE_ID}.f137.mp4").write_bytes(b"stream")
            return result

    return FakeYoutubeDL


def test_a_download_lands_in_the_master_folder_and_reports_the_source(storage: Path) -> None:
    ydl = fake_ydl(
        {
            "title": "Town hall",
            "uploader": "LGU",
            "upload_date": "20261101",
            "extractor_key": "Youtube",
            "webpage_url": "https://youtu.be/1",
            "duration": 90,
        }
    )
    progress: list[int] = []

    payload = importer.download(request(), progress.append, ydl)

    assert payload["path"] == f"records/{RECORD_ID}/master/{FILE_ID}.mp4"
    assert payload["file_size"] == 5
    assert payload["source"]["title"] == "Town hall"
    assert progress[0] == 47
    assert progress[-1] == 100
    assert "-generic" in ydl.options["allowed_extractors"]
    assert ydl.options["max_filesize"] == 10_000_000


def test_playlists_and_over_long_videos_are_refused_before_downloading(storage: Path) -> None:
    with pytest.raises(ImportFailed, match="playlist"):
        importer.download(request(), lambda _: None, fake_ydl({"_type": "playlist"}))
    with pytest.raises(ImportFailed, match="limit is 10"):
        importer.download(request(), lambda _: None, fake_ydl({"duration": 3600}))


def test_nothing_downloaded_is_reported_as_a_failure(storage: Path) -> None:
    with pytest.raises(ImportFailed, match="larger than the 9 MB limit"):
        importer.download(request(), lambda _: None, fake_ydl({"duration": 60}, write=False))


def test_a_failed_download_reports_why_and_cleans_up(
    storage: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    posted: list[tuple[str, dict[str, Any]]] = []
    monkeypatch.setattr(importer, "post_with_retry", lambda path, body: posted.append((path, body)))

    def failing(req: ImportRequest, progress: Any) -> dict[str, Any]:
        folder = storage / f"records/{RECORD_ID}/master"
        folder.mkdir(parents=True)
        (folder / f"{FILE_ID}.mp4.part").write_bytes(b"x")
        raise RuntimeError("ERROR: [youtube] abc: Video unavailable")

    monkeypatch.setattr(importer, "download", failing)

    importer.run_import(request(), "run-1", lambda _: None)

    assert posted == [
        (
            f"/internal/imports/{RECORD_ID}/done",
            {
                "status": "failed",
                "error": "The video could not be downloaded: abc: Video unavailable",
            },
        )
    ]
    assert list((storage / f"records/{RECORD_ID}/master").iterdir()) == []


def test_the_api_only_downloads_into_the_records_master_folder(
    client: TestClient, storage: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    monkeypatch.setattr(jobs, "start_import", lambda body: "run-9")

    def post(output_dir: str) -> Any:
        payload = json.dumps({**request().model_dump(), "output_dir": output_dir}).encode()
        return client.post(
            "/import-url",
            content=payload,
            headers={"Content-Type": "application/json", **signed_headers(SECRET, payload)},
        )

    assert post("records/other/master").status_code == 422
    accepted = post(f"records/{RECORD_ID}/master")
    assert accepted.status_code == 202
    assert accepted.json() == {"job_id": "run-9"}
