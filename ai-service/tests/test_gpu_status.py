import subprocess
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app import gpu_status


@pytest.fixture(autouse=True)
def _empty_store() -> None:
    gpu_status._memory.values.clear()


def test_health_reports_the_gpu_the_worker_published(client: TestClient) -> None:
    gpu_status.publish("NVIDIA GeForce RTX 3050 6GB Laptop GPU")

    body = client.get("/health").json()

    assert body["gpu"] is True
    assert body["gpu_name"] == "NVIDIA GeForce RTX 3050 6GB Laptop GPU"


def test_health_reports_no_gpu_when_no_worker_published_one(client: TestClient) -> None:
    body = client.get("/health").json()

    assert body["gpu"] is False
    assert body["gpu_name"] is None


def test_health_survives_an_unreachable_store(
    client: TestClient, monkeypatch: pytest.MonkeyPatch
) -> None:
    class Broken(gpu_status.MemoryStore):
        def get(self, key: str) -> str | None:
            raise ConnectionError("redis down")

    monkeypatch.setattr(gpu_status, "get_store", lambda settings=None: Broken())

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["gpu"] is False


def test_the_heartbeat_publishes_and_withdraws_on_stop() -> None:
    store = gpu_status.MemoryStore()
    heartbeat = gpu_status.Heartbeat("RTX", store)

    heartbeat.start()
    heartbeat._thread.join(timeout=0.2)  # first publish happens straight away
    assert store.get(gpu_status.KEY) == "RTX"

    heartbeat.stop()
    assert store.get(gpu_status.KEY) is None


def test_detect_reads_the_first_gpu_name(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(gpu_status.shutil, "which", lambda _: "/usr/bin/nvidia-smi")

    def fake_run(*args: Any, **kwargs: Any) -> subprocess.CompletedProcess[str]:
        return subprocess.CompletedProcess(args, 0, stdout="RTX 3050\nRTX 4090\n", stderr="")

    monkeypatch.setattr(gpu_status.subprocess, "run", fake_run)

    assert gpu_status.detect_gpu_name() == "RTX 3050"


def test_detect_finds_nothing_without_nvidia_smi_or_when_it_fails(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr(gpu_status.shutil, "which", lambda _: None)
    assert gpu_status.detect_gpu_name() is None

    monkeypatch.setattr(gpu_status.shutil, "which", lambda _: "/usr/bin/nvidia-smi")

    def failing_run(*args: Any, **kwargs: Any) -> None:
        raise subprocess.CalledProcessError(9, "nvidia-smi")

    monkeypatch.setattr(gpu_status.subprocess, "run", failing_run)
    assert gpu_status.detect_gpu_name() is None
