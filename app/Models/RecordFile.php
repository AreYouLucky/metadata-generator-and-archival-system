<?php

namespace App\Models;

use App\Enums\ChecksumStatus;
use App\Enums\FileRole;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\RecordFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A stored file of a record (master, mezzanine, proxy, access). Checksums are written by the pipeline only.
 *
 * @property string $id
 * @property string $record_id
 * @property FileRole $role
 * @property string $storage_location
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property string|null $md5_checksum
 * @property string|null $sha256_checksum
 * @property string|null $expected_md5
 * @property string|null $expected_sha256
 * @property ChecksumStatus $checksum_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['role', 'storage_location', 'original_filename', 'mime_type', 'file_size', 'expected_md5', 'expected_sha256'])]
#[Hidden(['storage_location'])]
class RecordFile extends Model
{
    /** @use HasFactory<RecordFileFactory> */
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
            'role' => FileRole::class,
            'file_size' => 'integer',
            'checksum_status' => ChecksumStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * @return HasOne<RecordTechnicalMetadata, $this>
     */
    public function technicalMetadata(): HasOne
    {
        return $this->hasOne(RecordTechnicalMetadata::class);
    }

    /**
     * @return HasMany<FixityCheck, $this>
     */
    public function fixityChecks(): HasMany
    {
        return $this->hasMany(FixityCheck::class)->orderBy('checked_at');
    }
}
