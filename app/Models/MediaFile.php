<?php

namespace App\Models;

use App\Enums\ChecksumStatus;
use App\Enums\MediaFileRole;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A stored file version (master, mezzanine, proxy, access) and its technical record.
 *
 * @property string $id
 * @property string $project_id
 * @property MediaFileRole $role
 * @property string $path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $container
 * @property string|null $container_long
 * @property float|null $duration_sec
 * @property int|null $bitrate
 * @property string|null $start_timecode
 * @property string|null $md5
 * @property string|null $sha256
 * @property string|null $expected_md5
 * @property string|null $expected_sha256
 * @property ChecksumStatus $checksum_status
 * @property array<string, mixed>|null $raw_probe
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['role', 'path', 'original_filename', 'mime_type', 'size_bytes', 'container', 'container_long', 'duration_sec', 'bitrate', 'start_timecode', 'expected_md5', 'expected_sha256', 'raw_probe'])]
#[Hidden(['path', 'raw_probe'])]
class MediaFile extends Model
{
    /** @use HasFactory<MediaFileFactory> */
    use HasFactory, HasUlids;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'checksum_status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MediaFileRole::class,
            'size_bytes' => 'integer',
            'duration_sec' => 'float',
            'bitrate' => 'integer',
            'checksum_status' => ChecksumStatus::class,
            'raw_probe' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<MediaStream, $this>
     */
    public function streams(): HasMany
    {
        return $this->hasMany(MediaStream::class)->orderBy('stream_index');
    }

    /**
     * @return HasMany<FixityCheck, $this>
     */
    public function fixityChecks(): HasMany
    {
        return $this->hasMany(FixityCheck::class)->orderBy('checked_at');
    }
}
