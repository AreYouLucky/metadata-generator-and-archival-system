import hashlib
from pathlib import Path

from app.pipeline.checksum import compute
from app.pipeline.probe import summarise

PRORES_PROBE = {
    "format": {
        "format_name": "mov,mp4,m4a,3gp,3g2,mj2",
        "format_long_name": "QuickTime / MOV",
        "duration": "312.500000",
        "bit_rate": "4500000",
        "tags": {"creation_time": "2026-07-10T08:00:00.000000Z"},
    },
    "streams": [
        {
            "codec_type": "video",
            "codec_name": "prores",
            "profile": "HQ",
            "width": 1920,
            "height": 1080,
            "r_frame_rate": "25/1",
            "display_aspect_ratio": "16:9",
            "field_order": "tt",
            "pix_fmt": "yuv422p10le",
            "color_space": "bt709",
            "color_primaries": "bt709",
            "color_transfer": "bt709",
        },
        {
            "codec_type": "audio",
            "codec_name": "pcm_s24le",
            "channels": 2,
            "sample_rate": "48000",
            "tags": {"language": "eng"},
        },
        {"codec_type": "data", "codec_tag_string": "tmcd", "tags": {"timecode": "01:00:00:00"}},
    ],
}


def test_ffprobe_output_maps_onto_the_technical_metadata_columns() -> None:
    result = summarise(PRORES_PROBE, {})

    assert result["file_format"] == "QuickTime / MOV"
    assert result["duration"] == 312.5
    assert result["video_codec"] == "prores"
    assert result["resolution"] == "1920x1080"
    assert result["scan_type"] == "interlaced_tff"
    assert result["bit_depth"] == 10
    assert result["chroma_subsampling"] == "4:2:2"
    assert result["start_timecode"] == "01:00:00:00"
    assert result["audio_tracks"] == [
        {"index": 1, "codec": "pcm_s24le", "channels": 2, "sampleRate": 48000, "language": "eng"}
    ]
    assert result["container_tags"] == {"creation_time": "2026-07-10T08:00:00.000000Z"}


def test_mediainfo_fills_the_scan_type_when_ffprobe_has_no_field_order() -> None:
    probe = {"format": {}, "streams": [{"codec_type": "video", "codec_name": "mpeg2video"}]}
    mediainfo = {
        "tracks": [{"track_type": "Video", "scan_type": "Interlaced", "scan_order": "BFF"}]
    }

    assert summarise(probe, mediainfo)["scan_type"] == "interlaced_bff"


def test_unknown_values_stay_unknown_instead_of_being_guessed() -> None:
    probe = {
        "format": {},
        "streams": [{"codec_type": "video", "codec_name": "h264", "pix_fmt": ""}],
    }

    result = summarise(probe, {})

    assert result["scan_type"] == "unknown"
    assert result["bit_depth"] is None
    assert result["chroma_subsampling"] is None
    assert result["start_timecode"] == "00:00:00:00"
    assert result["audio_codec"] is None


def test_checksums_match_hashlib_in_a_single_pass(tmp_path: Path) -> None:
    video = tmp_path / "video.bin"
    video.write_bytes(b"archive" * 10_000)
    seen: list[int] = []

    result = compute(video, seen.append)

    assert result["md5"] == hashlib.md5(video.read_bytes(), usedforsecurity=False).hexdigest()
    assert result["sha256"] == hashlib.sha256(video.read_bytes()).hexdigest()
    assert result["file_size"] == 70_000
    assert seen[-1] == 100
