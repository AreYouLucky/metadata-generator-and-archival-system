<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PingSource;
use App\Enums\PingTarget;
use App\Events\SystemPingReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSystemPingRequest;
use App\Services\AiService\AiServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SystemPingController extends Controller
{
    /**
     * Start a test ping. The result arrives in the browser as a
     * `system.ping` broadcast on the private `admin.system` channel.
     */
    public function __invoke(SendSystemPingRequest $request, AiServiceClient $client): JsonResponse
    {
        $pingId = (string) Str::uuid();

        if ($request->target() === PingTarget::Broadcast) {
            SystemPingReceived::dispatch($pingId, PingSource::Laravel, now()->toIso8601String());

            return response()->json(['ping_id' => $pingId], Response::HTTP_ACCEPTED);
        }

        try {
            $jobId = $client->ping($pingId);
        } catch (ConnectionException|RequestException) {
            return response()->json(
                ['message' => 'The AI service did not accept the ping. Check that ai-api is running.'],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return response()->json(['ping_id' => $pingId, 'job_id' => $jobId], Response::HTTP_ACCEPTED);
    }
}
