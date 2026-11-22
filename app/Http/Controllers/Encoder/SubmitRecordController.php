<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SubmitRecordRequest;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\ReviewWorkflow;
use App\Services\Records\SuggestionConfirmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SubmitRecordController extends Controller
{
    /**
     * Send a draft (or a record with changes requested) to the admins'
     * review queue. From then on it is read-only for the encoder. Only a
     * finished record can be submitted: there is nothing to review while the
     * transcript and metadata are still being made.
     */
    public function __invoke(SubmitRecordRequest $request, Record $record, ReviewWorkflow $workflow, SuggestionConfirmer $suggestions): JsonResponse
    {
        abort_unless(
            $record->processing_status === ProcessingStatus::Ready,
            422,
            'Wait until processing has finished before submitting this record.',
        );

        /** @var User $user */
        $user = $request->user();

        // One transaction: if the submit is refused, the suggestions stay unconfirmed too.
        DB::transaction(function () use ($request, $record, $user, $workflow, $suggestions): void {
            if ($request->boolean('confirm_suggestions')) {
                $suggestions->confirmAll($record, $user);
            }

            abort_unless(
                $workflow->submit($record, $user, $request->validated('notes')),
                409,
                'This record has already been submitted.',
            );
        });

        return response()->json(['review_status' => $record->review_status->value]);
    }
}
