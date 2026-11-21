<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Models\Record;
use App\Models\User;
use App\Models\VideoAuditLog;

/**
 * Appends to the archive audit log (`video_audit_logs`, append-only).
 */
class AuditTrail
{
    public function log(Record $record, AuditAction $action, ?User $user, ?string $remarks = null, ?string $fileId = null): void
    {
        VideoAuditLog::create([
            'record_id' => $record->id,
            'record_file_id' => $fileId,
            'action' => $action,
            'performed_by' => $user?->id,
            'performed_at' => now(),
            'remarks' => $remarks === null ? null : mb_substr($remarks, 0, 2000),
        ]);
    }
}
