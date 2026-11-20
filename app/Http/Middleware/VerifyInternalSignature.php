<?php

namespace App\Http\Middleware;

use App\Services\InternalSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /internal routes the Python service calls back on. The request
 * must carry a fresh HMAC signature of its exact raw body, and each signature
 * is accepted only once inside the freshness window (replay protection).
 */
class VerifyInternalSignature
{
    public function __construct(private InternalSignature $signature) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header(InternalSignature::TIMESTAMP_HEADER);
        $signature = $request->header(InternalSignature::SIGNATURE_HEADER);

        if (! $this->signature->verify($timestamp, $signature, $request->getContent())) {
            return $this->reject($request, 'invalid or expired signature');
        }

        $isFirstUse = Cache::add(
            'internal-signature:'.hash('sha256', (string) $signature),
            true,
            $this->signature->maxAgeSeconds() * 2,
        );

        if (! $isFirstUse) {
            return $this->reject($request, 'replayed signature');
        }

        return $next($request);
    }

    private function reject(Request $request, string $reason): Response
    {
        Log::warning('Rejected internal request', [
            'reason' => $reason,
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
    }
}
