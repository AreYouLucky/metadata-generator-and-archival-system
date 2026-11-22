<?php

namespace App\Services\Chat;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Record;
use App\Models\User;
use App\Services\Catalogue\Distribution;
use Illuminate\Database\Eloquent\Builder;

/**
 * What one account may ask "Ask me" about (owner's rule): approved records
 * only, never drafts or records under review, and of those only what the
 * account may see.
 *
 * - Admins: every approved record, with its internal fields.
 * - Encoders: their own approved records with internal fields, plus every
 *   record in the public catalogue with its public fields.
 * - Viewers (`user`): the public catalogue only (RecordPolicy::distribute:
 *   unclassified, rights public, no embargo), with its public fields.
 *
 * "Internal" means the account may open the record in the workspace
 * (RecordPolicy::view); otherwise tools return only what the public watch
 * page shows.
 */
class ChatScope
{
    /** @var array<string, bool> */
    private array $internal = [];

    public function __construct(public readonly User $user) {}

    /**
     * @return Builder<Record>
     */
    public function records(): Builder
    {
        $query = Record::query()
            ->where('records.review_status', ReviewStatus::Approved)
            ->whereNotNull('records.published_at');

        if (! $this->user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->user->isAdmin()) {
            return $query;
        }

        if ($this->user->role === UserRole::Encoder) {
            return $query->where(fn (Builder $visible) => $visible
                ->where('records.created_by', $this->user->id)
                ->orWhere(fn (Builder $public) => Distribution::constrain($public)));
        }

        return Distribution::constrain($query);
    }

    /**
     * The record if this account may ask about it, else null. IDs come from
     * the model or the page, so they are always checked here.
     */
    public function find(?string $recordId): ?Record
    {
        if ($recordId === null || $recordId === '' || strlen($recordId) !== 26) {
            return null;
        }

        return $this->records()->whereKey(strtolower($recordId))->first();
    }

    /**
     * Whether answers may use the record's internal fields (the account may
     * open it in the workspace) or only its public ones.
     */
    public function seesInternal(Record $record): bool
    {
        return $this->internal[$record->id] ??= $this->user->can('view', $record);
    }

    /**
     * Where a person goes to watch the record: the workspace for staff who
     * may open it, the public watch page otherwise.
     */
    public function url(Record $record, ?float $seconds = null): string
    {
        $query = $seconds === null ? [] : ['t' => (int) floor($seconds)];

        return $this->seesInternal($record)
            ? route('records.show', ['record' => $record->id, ...$query])
            : route('catalogue.watch', ['record' => $record->id, ...$query]);
    }
}
