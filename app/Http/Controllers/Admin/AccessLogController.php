<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexLogsRequest;
use App\Models\Record;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AccessLogController extends Controller
{
    /**
     * `GET /admin/records/{record}/access-log` (PROJECT_SPEC §8): who viewed
     * or streamed a record. The Logs page already lists the access log, so
     * this opens it filtered to the record.
     */
    public function __invoke(Record $record): RedirectResponse
    {
        Gate::authorize('viewAccessLog', $record);

        return to_route('admin.logs.index', ['type' => IndexLogsRequest::ACCESS, 'record' => $record->id]);
    }
}
