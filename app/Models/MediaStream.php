<?php

namespace App\Models;

use App\Enums\ScanType;
use App\Enums\StreamType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stream (video, audio, subtitle, data) inside a media file.
 *
 * @property int $id
 * @property string $media_file_id
 * @property int $stream_index
 * @property StreamType $type
 * @property string|null $codec
 * @property string|null $codec_long
 * @property string|null $profile
 * @property int|null $bit_rate
 * @property string|null $language
 * @property float|null $duration_sec
 * @property int|null $width
 * @property int|null $height
 * @property string|null $display_aspect_ratio
 * @property string|null $frame_rate
 * @property ScanType|null $scan_type
 * @property string|null $pix_fmt
 * @property string|null $chroma_subsampling
 * @property string|null $color_space
 * @property string|null $color_primaries
 * @property string|null $color_transfer
 * @property string|null $color_range
 * @property int|null $bit_depth
 * @property int|null $channels
 * @property string|null $channel_layout
 * @property int|null $sample_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['stream_index', 'type', 'codec', 'codec_long', 'profile', 'bit_rate', 'language', 'duration_sec', 'width', 'height', 'display_aspect_ratio', 'frame_rate', 'scan_type', 'pix_fmt', 'chroma_subsampling', 'color_space', 'color_primaries', 'color_transfer', 'color_range', 'bit_depth', 'channels', 'channel_layout', 'sample_rate'])]
class MediaStream extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stream_index' => 'integer',
            'type' => StreamType::class,
            'bit_rate' => 'integer',
            'duration_sec' => 'float',
            'width' => 'integer',
            'height' => 'integer',
            'scan_type' => ScanType::class,
            'bit_depth' => 'integer',
            'channels' => 'integer',
            'sample_rate' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
