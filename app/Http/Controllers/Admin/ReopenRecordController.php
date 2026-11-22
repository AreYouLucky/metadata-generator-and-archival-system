<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReopenRecordRequest;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\ReviewWorkflow;
use Illuminate\Http\JsonResponse;

class ReopenRecordController extends Controller
{
    /**
     * Back to draft: an approved record leaves the public catalogue until it
     * is approved again; a rejected one can be worked on again.
     */
    public function __invoke(ReopenRecordRequest $request, Record $record, ReviewWorkflow $workflow): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        abort_unless(
            $workflow->reopen($record, $admin, $request->validated('notes')),
            409,
            'Only an approved or rejected record can be reopened.',
        );

        return response()->json(['review_status' => $record->review_status->value]);
    }
}
