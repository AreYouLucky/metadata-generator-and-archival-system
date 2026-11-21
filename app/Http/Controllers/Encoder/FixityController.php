<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\FixityTrigger;
use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Services\Records\FixityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FixityController extends Controller
{
    /**
     * "Check now" (Provenance tab): re-hash the master and mezzanine files.
     * Anyone who may see the record may check it; nothing is changed unless
     * a file no longer matches.
     */
    public function __invoke(Record $record, FixityRecorder $fixity): JsonResponse
    {
        Gate::authorize('view', $record);

        $queued = $fixity->queue($record, FixityTrigger::User);
        abort_if($queued === 0, 422, 'No file has ingest checksums to check against yet.');

        return response()->json(['queued' => $queued], 202);
    }
}
