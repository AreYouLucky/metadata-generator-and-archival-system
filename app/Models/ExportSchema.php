<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Database\Factories\ExportSchemaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's custom JSON export: the dot-paths of the §10 document to keep
 * (see App\Exporters\ExportSchemaFields for the allowed paths).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property list<string> $fields
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'fields', 'is_default'])]
class ExportSchema extends Model
{
    /** @use HasFactory<ExportSchemaFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
