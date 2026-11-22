<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\GenerateTitlesRequest;
use App\Http\Requests\Internal\ReceiveStageResultRequest;
use App\Jobs\GenerateTitlesJob;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TitleController extends Controller
{
    /**
     * Queue "Generate more" titles. The new options arrive over Reverb
     * (`record.titles-generated`) and are already saved on the generation.
     */
    public function __invoke(GenerateTitlesRequest $request, Record $record): JsonResponse
    {
        $metadata = $record->activeMetadata()->first(['id', 'record_id', 'payload']);

        abort_if($metadata === null || $metadata->id !== (int) $request->validated('metadata_id'), 409, 'The metadata changed; reload the record and try again.');
        abort_if(trim((string) ($metadata->payload['summary'] ?? '')) === '', 422, 'This generation has no summary to write titles from. Regenerate the metadata first.');
        abort_if(count($metadata->payload['titles'] ?? []) >= ReceiveStageResultRequest::MAX_TITLES, 422, 'This generation already has the most title options it can hold.');
        abort_unless(Cache::add(GenerateTitlesJob::lockKey($record), true, GenerateTitlesJob::LOCK_SECONDS), 409, 'More titles are already being written.');

        GenerateTitlesJob::dispatch($record, $metadata->id);

        return response()->json(['queued' => true], 202);
    }
}
