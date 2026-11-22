<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\ConfirmSuggestionsRequest;
use App\Models\Record;
use App\Services\Records\SuggestionConfirmer;
use Illuminate\Http\JsonResponse;

class ConfirmSuggestionsController extends Controller
{
    /**
     * "Confirm all": every pending AI subject and keyword suggestion in one
     * request (one audit entry), instead of one request per chip.
     */
    public function __invoke(ConfirmSuggestionsRequest $request, Record $record, SuggestionConfirmer $suggestions): JsonResponse
    {
        return response()->json(['confirmed' => $suggestions->confirmAll($record, $request->user())]);
    }
}
