<?php

namespace App\Models;

use App\Enums\AccessAction;
use App\Models\Concerns\AppendOnly;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who viewed, streamed or downloaded a record. Append-only.
 *
 * @property int $id
 * @property string $record_id
 * @property int|null $user_id
 * @property AccessAction $action
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $accessed_at
 */
#[Fillable(['record_id', 'user_id', 'action', 'ip_address', 'user_agent', 'accessed_at'])]
class AccessLog extends Model
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
            'action' => AccessAction::class,
            'accessed_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
