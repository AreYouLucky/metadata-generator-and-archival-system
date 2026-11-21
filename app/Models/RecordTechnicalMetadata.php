<?php

namespace App\Models;

use App\Enums\ScanType;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one record file is encoded. Written by the probe step; read-only in the app.
 *
 * @property int $id
 * @property string $record_file_id
 * @property string|null $file_format
 * @property float|null $duration
 * @property int|null $bitrate
 * @property string|null $start_timecode
 * @property string|null $video_codec
 * @property string|null $video_profile
 * @property string|null $resolution
 * @property string|null $frame_rate
 * @property string|null $aspect_ratio
 * @property ScanType|null $scan_type
 * @property int|null $bit_depth
 * @property string|null $chroma_subsampling
 * @property string|null $color_space
 * @property string|null $color_primaries
 * @property string|null $color_transfer
 * @property string|null $audio_codec
 * @property int|null $audio_channels
 * @property int|null $audio_sample_rate
 * @property array<int, array<string, mixed>>|null $audio_tracks
 * @property array<string, mixed>|null $raw_probe
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['file_format', 'duration', 'bitrate', 'start_timecode', 'video_codec', 'video_profile', 'resolution', 'frame_rate', 'aspect_ratio', 'scan_type', 'bit_depth', 'chroma_subsampling', 'color_space', 'color_primaries', 'color_transfer', 'audio_codec', 'audio_channels', 'audio_sample_rate', 'audio_tracks', 'raw_probe'])]
#[Hidden(['raw_probe'])]
class RecordTechnicalMetadata extends Model
{
    protected $table = 'record_technical_metadata';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'float',
            'bitrate' => 'integer',
            'scan_type' => ScanType::class,
            'bit_depth' => 'integer',
            'audio_channels' => 'integer',
            'audio_sample_rate' => 'integer',
            'audio_tracks' => 'array',
            'raw_probe' => 'array',
        ];
    }

    /**
     * Whether browsers can play the file directly: an MP4/MOV/WebM container with
     * H.264, VP8/9 or AV1 video and AAC, MP3, Opus or Vorbis audio. ProRes, DNxHD,
     * MPEG-2, MXF and the like need the proxy (Phase 3).
     */
    public function isBrowserPlayable(?string $mimeType): bool
    {
        $container = in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v'], true);
        $video = $this->video_codec === null || in_array($this->video_codec, ['h264', 'vp8', 'vp9', 'av1'], true);
        $audio = $this->audio_codec === null || in_array($this->audio_codec, ['aac', 'mp3', 'opus', 'vorbis'], true);

        return $container && $video && $audio;
    }

    /**
     * @return BelongsTo<RecordFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(RecordFile::class, 'record_file_id');
    }
}
