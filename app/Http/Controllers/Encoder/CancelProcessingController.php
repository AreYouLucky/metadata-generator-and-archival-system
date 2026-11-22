<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\CancelProcessingRequest;
use App\Models\Record;
use App\Services\Records\ProcessingCanceller;
use Illuminate\Http\JsonResponse;

class CancelProcessingController extends Controller
{
    /**
     * Stop a queued or running pipeline. Results already stored stay; "Retry"
     * runs every stage again.
     */
    public function __invoke(CancelProcessingRequest $request, Record $record, ProcessingCanceller $canceller): JsonResponse
    {
        abort_unless($canceller->cancel($record, $request->user()), 409, 'Only a record that is still processing can be cancelled.');

        return response()->json(['cancelled' => true]);
    }
}
