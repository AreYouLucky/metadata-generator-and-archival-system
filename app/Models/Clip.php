<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Services\Records\VisualsPresenter;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\ClipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part of a record's master cut to its own MP4 (Phase 7), checksummed and
 * probed. The workflow fields (status, file, checksums) are set by the
 * system, never mass-assigned.
 *
 * @property int $id
 * @property string $record_id
 * @property int|null $created_by
 * @property string|null $title
 * @property float $start
 * @property float $end
 * @property TaskStatus $status
 * @property string|null $path
 * @property int|null $file_size
 * @property string|null $md5_checksum
 * @property string|null $sha256_checksum
 * @property array<string, mixed>|null $technical
 * @property string|null $error
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'start', 'end'])]
class Clip extends Model
{
    /** @use HasFactory<ClipFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => 'float',
            'end' => 'float',
            'status' => TaskStatus::class,
            'file_size' => 'integer',
            'technical' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Where the AI service writes this clip (relative to the media root).
     */
    public function storagePath(): string
    {
        return "records/{$this->record_id}/clips/{$this->id}.mp4";
    }

    public function isBusy(): bool
    {
        return in_array($this->status, [TaskStatus::Queued, TaskStatus::Processing], true);
    }

    /**
     * "Clip 1:05–1:40", for names and the audit log.
     */
    public function label(): string
    {
        $range = VisualsPresenter::clock($this->start).'–'.VisualsPresenter::clock($this->end);

        return $this->title === null || $this->title === '' ? "Clip {$range}" : "{$this->title} ({$range})";
    }

    /**
     * The fields the Clips panel shows.
     *
     * @return array{id: int, title: string|null, start: float, end: float, status: string, file_size: int|null, sha256: string|null, error: string|null, technical: array<string, mixed>|null, download_url: string|null, created_at: string|null}
     */
    public function present(): array
    {
        $ready = $this->status === TaskStatus::Done;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'start' => $this->start,
            'end' => $this->end,
            'status' => $this->status->value,
            'file_size' => $this->file_size,
            'sha256' => $this->sha256_checksum,
            'error' => $this->error,
            'technical' => $this->technical,
            'download_url' => $ready ? route('records.clips.show', ['record' => $this->record_id, 'clip' => $this->id]) : null,
            'created_at' => $this->created_at?->toIso8601String(),
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
