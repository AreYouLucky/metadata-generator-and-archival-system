<?php

namespace App\Models;

use App\Enums\UsageKind;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Metered usage (minutes, tokens, storage) attributed to an encoder. Append-only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $project_id
 * @property UsageKind $kind
 * @property float $quantity
 * @property float|null $estimated_cost
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'project_id', 'kind', 'quantity', 'estimated_cost', 'occurred_at'])]
class UsageRecord extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => UsageKind::class,
            'quantity' => 'float',
            'estimated_cost' => 'float',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
