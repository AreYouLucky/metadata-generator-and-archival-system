<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A window of transcript text with its embedding (PostgreSQL only).
 *
 * @property int $id
 * @property string $project_id
 * @property float $start
 * @property float $end
 * @property string $text
 * @property array<int, float>|null $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['start', 'end', 'text', 'embedding'])]
#[Hidden(['embedding'])]
class TranscriptChunk extends Model
{
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
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
