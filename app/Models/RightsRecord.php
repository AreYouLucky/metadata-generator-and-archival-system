<?php

namespace App\Models;

use App\Enums\Licence;
use Database\Factories\RightsRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Administrative and rights metadata (who owns it, how it can be used).
 *
 * @property int $id
 * @property string $project_id
 * @property string|null $copyright_holder
 * @property int|null $copyright_year
 * @property Licence $licence
 * @property string|null $licence_text
 * @property string|null $licence_url
 * @property string|null $restrictions
 * @property array<int, string>|null $territories
 * @property Carbon|null $embargo_until
 * @property array<int, array{role: string, name: string}>|null $credits
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['copyright_holder', 'copyright_year', 'licence', 'licence_text', 'licence_url', 'restrictions', 'territories', 'embargo_until', 'credits', 'notes'])]
class RightsRecord extends Model
{
    /** @use HasFactory<RightsRecordFactory> */
    use HasFactory;

    /**
     * Defaults mirrored from the migration so new models see them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'licence' => 'all_rights_reserved',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copyright_year' => 'integer',
            'licence' => Licence::class,
            'territories' => 'array',
            'embargo_until' => 'datetime',
            'credits' => 'array',
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
     * Whether the embargo is still in force.
     */
    public function isEmbargoed(): bool
    {
        return $this->embargo_until !== null && $this->embargo_until->isFuture();
    }
}
