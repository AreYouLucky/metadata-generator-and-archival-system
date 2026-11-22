<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Models\Record;
use App\Models\VideoAuditLog;

/**
 * A record's review steps (submitted, approved, changes requested, rejected,
 * reopened) with their notes, newest first, from the audit log. Shown in the
 * workspace and on the admin review page.
 */
class ReviewHistory
{
    private const int LIMIT = 30;

    private const array ACTIONS = [
        AuditAction::Submitted,
        AuditAction::Approved,
        AuditAction::ChangesRequested,
        AuditAction::Rejected,
        AuditAction::Reopened,
    ];

    /**
     * @return list<array{id: int, action: string, by: string|null, at: string, notes: string|null}>
     */
    public function for(Record $record): array
    {
        return array_values(VideoAuditLog::query()
            ->select(['id', 'record_id', 'action', 'performed_by', 'performed_at', 'remarks'])
            ->where('record_id', $record->id)
            ->whereIn('action', self::ACTIONS)
            ->with('performer:id,name')
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (VideoAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action->value,
                'by' => $log->performer?->name,
                'at' => $log->performed_at->toIso8601String(),
                'notes' => $log->remarks,
            ])
            ->all());
    }
}
