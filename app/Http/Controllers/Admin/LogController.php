<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessAction;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexLogsRequest;
use App\Models\AccessLog;
use App\Models\Record;
use App\Models\VideoAuditLog;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Logs: the archive audit log (uploads, reviews, preservation events)
 * and the access log (views, streams, downloads, exports), newest first.
 * IP addresses appear here only, as this page is admin-only (PROJECT_SPEC §5).
 */
class LogController extends Controller
{
    private const int PER_PAGE = 30;

    public function __invoke(IndexLogsRequest $request): Response
    {
        $type = $request->validated('type') ?? IndexLogsRequest::AUDIT;
        $action = $request->validated('action');
        $recordId = $request->validated('record');

        $logs = $type === IndexLogsRequest::ACCESS ? $this->access($action, $recordId) : $this->audit($action, $recordId);

        return Inertia::render('admin/logs-page/logs', [
            'logs' => $logs->withQueryString(),
            'filters' => [
                'type' => $type,
                'action' => $action,
                'record' => $recordId === null ? null : $this->recordSummary($recordId, Record::withTrashed()->whereKey($recordId)->first(['id', 'title', 'deleted_at'])),
            ],
            'actions' => array_column($type === IndexLogsRequest::ACCESS ? AccessAction::cases() : AuditAction::cases(), 'value'),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function audit(?string $action, ?string $recordId): LengthAwarePaginator
    {
        return VideoAuditLog::query()
            ->select(['id', 'record_id', 'action', 'performed_by', 'performed_at', 'remarks'])
            ->when($action, fn (Builder $query, string $value) => $query->where('action', $value))
            ->when($recordId, fn (Builder $query, string $value) => $query->where('record_id', $value))
            ->with(['record' => $this->recordTitle(), 'performer:id,name'])
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (VideoAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action->value,
                'record' => $this->recordSummary($log->record_id, $log->record),
                'user' => $log->performer?->name,
                'at' => $log->performed_at->toIso8601String(),
                'details' => $log->remarks,
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function access(?string $action, ?string $recordId): LengthAwarePaginator
    {
        return AccessLog::query()
            ->select(['id', 'record_id', 'user_id', 'action', 'ip_address', 'accessed_at'])
            ->when($action, fn (Builder $query, string $value) => $query->where('action', $value))
            ->when($recordId, fn (Builder $query, string $value) => $query->where('record_id', $value))
            ->with(['record' => $this->recordTitle(), 'user:id,name'])
            ->orderByDesc('accessed_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (AccessLog $log): array => [
                'id' => $log->id,
                'action' => $log->action->value,
                'record' => $this->recordSummary($log->record_id, $log->record),
                'user' => $log->user?->name,
                'at' => $log->accessed_at->toIso8601String(),
                'details' => $log->ip_address,
            ]);
    }

    /**
     * Titles of deleted records too: the logs outlive them.
     */
    private function recordTitle(): Closure
    {
        return fn (BelongsTo $query) => $query->withTrashed()->select(['id', 'title', 'deleted_at']);
    }

    /**
     * @return array{id: string, title: string|null, deleted: bool}
     */
    private function recordSummary(string $recordId, ?Record $record): array
    {
        return [
            'id' => $recordId,
            'title' => $record?->title,
            'deleted' => $record === null || $record->deleted_at !== null,
        ];
    }
}
