<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Database\Factories\RecordFrameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A key frame with its visual analysis. Face counts only; no face recognition.
 *
 * @property int $id
 * @property string $record_id
 * @property float $time_sec
 * @property string $path
 * @property bool $is_scene_start
 * @property string|null $description
 * @property string|null $ocr_text
 * @property array<int, array{label: string, confidence: float}>|null $objects
 * @property int|null $face_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['time_sec', 'path', 'is_scene_start', 'description', 'ocr_text', 'objects', 'face_count'])]
class RecordFrame extends Model
{
    /** @use HasFactory<RecordFrameFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_scene_start' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_sec' => 'float',
            'is_scene_start' => 'boolean',
            'objects' => 'array',
            'face_count' => 'integer',
        ];
    }

    /**
     * How the AI service names a frame: its time, to the millisecond (the
     * column's precision).
     */
    public static function timeKey(float $seconds): string
    {
        return number_format($seconds, 3, '.', '');
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
