<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How the record's file is encoded. Written by the probe and checksum steps.
 *
 * @property int $id
 * @property string $record_id
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property string|null $file_format
 * @property int|null $file_size
 * @property float|null $duration
 * @property string|null $video_codec
 * @property string|null $audio_codec
 * @property string|null $resolution
 * @property string|null $frame_rate
 * @property int|null $bitrate
 * @property string|null $aspect_ratio
 * @property int|null $audio_channels
 * @property int|null $audio_sample_rate
 * @property string|null $sha256_checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['original_filename', 'mime_type', 'file_format', 'file_size', 'duration', 'video_codec', 'audio_codec', 'resolution', 'frame_rate', 'bitrate', 'aspect_ratio', 'audio_channels', 'audio_sample_rate'])]
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
            'file_size' => 'integer',
            'duration' => 'float',
            'bitrate' => 'integer',
            'audio_channels' => 'integer',
            'audio_sample_rate' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
