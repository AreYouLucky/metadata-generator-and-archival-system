<?php

namespace App\Models;

use App\Enums\ReviewAction;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One action in a project's approval history. Append-only.
 *
 * @property int $id
 * @property string $project_id
 * @property int|null $actor_id
 * @property ReviewAction $action
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable(['actor_id', 'action', 'notes'])]
class ProjectReview extends Model
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
            'action' => ReviewAction::class,
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
