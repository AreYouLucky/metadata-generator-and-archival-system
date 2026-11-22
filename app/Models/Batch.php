<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\BatchZipStatus;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Several files or URLs uploaded together (Phase 6); each is its own record.
 * The status, counts and ZIP fields are set by the system, never mass-assigned.
 *
 * @property string $id
 * @property int $created_by
 * @property string|null $name
 * @property BatchStatus $status
 * @property int $total
 * @property int $completed
 * @property int $failed
 * @property string|null $zip_path
 * @property BatchZipStatus|null $zip_status
 * @property Carbon|null $zip_built_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'processing',
        'total' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'total' => 'integer',
            'completed' => 'integer',
            'failed' => 'integer',
            'zip_status' => BatchZipStatus::class,
            'zip_built_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Record, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    /**
     * The name shown in lists: the encoder's, else the upload date.
     */
    public function label(): string
    {
        return $this->name ?? 'Batch of '.$this->created_at?->toDayDateTimeString();
    }
}
