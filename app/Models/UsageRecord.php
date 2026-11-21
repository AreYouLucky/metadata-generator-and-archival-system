<?php

namespace App\Models;

use App\Enums\UsageKind;
use App\Models\Concerns\AppendOnly;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metered AI usage attributed to an encoder. Append-only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $record_id
 * @property UsageKind $kind
 * @property float $quantity
 * @property float|null $estimated_cost
 * @property Carbon $occurred_at
 */
#[Fillable(['user_id', 'record_id', 'kind', 'quantity', 'estimated_cost', 'occurred_at'])]
class UsageRecord extends Model
{
    use AppendOnly;

    public $timestamps = false;

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
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
