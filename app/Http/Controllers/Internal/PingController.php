<?php

namespace App\Http\Controllers\Internal;

use App\Enums\PingSource;
use App\Events\SystemPingReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ReceivePingRequest;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    /**
     * Signed test callback from the AI service worker. Re-broadcasts the ping
     * to admins so the System status page can show the full round trip.
     */
    public function __invoke(ReceivePingRequest $request): JsonResponse
    {
        SystemPingReceived::dispatch(
            $request->validated('ping_id'),
            PingSource::from($request->validated('source')),
            now()->toIso8601String(),
        );

        return response()->json(['ok' => true]);
    }
}
