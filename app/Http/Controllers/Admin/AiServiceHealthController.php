<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiService\AiServiceClient;
use Illuminate\Http\JsonResponse;

class AiServiceHealthController extends Controller
{
    public function __invoke(AiServiceClient $client): JsonResponse
    {
        return response()->json($client->health());
    }
}
