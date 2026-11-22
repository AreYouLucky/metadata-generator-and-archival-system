<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SuggestDescriptiveRequest;
use App\Jobs\SuggestDescriptiveJob;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DescriptiveSuggestionController extends Controller
{
    /**
     * Queue catalogue field suggestions. They arrive over Reverb
     * (`record.descriptive-suggested`) and fill the form unsaved, for review.
     */
    public function __invoke(SuggestDescriptiveRequest $request, Record $record): JsonResponse
    {
        $summary = $record->activeMetadata()->first(['id', 'record_id', 'payload'])?->payload['summary'] ?? null;

        abort_if(! is_string($summary) || trim($summary) === '', 422, 'Generate the AI metadata first: the suggestions are written from its summary.');
        abort_unless(Cache::add(SuggestDescriptiveJob::lockKey($record), true, SuggestDescriptiveJob::LOCK_SECONDS), 409, 'Suggestions are already being written.');

        SuggestDescriptiveJob::dispatch($record);

        return response()->json(['queued' => true], 202);
    }
}
