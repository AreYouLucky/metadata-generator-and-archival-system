<?php

namespace App\Services\AiService;

use App\Services\InternalSignature;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Typed HTTP client for the Python AI service. Every request is HMAC-signed
 * over its exact raw body (see InternalSignature).
 */
class AiServiceClient
{
    public function __construct(
        private InternalSignature $signature,
        #[Config('archive.ai_service.url')] private string $baseUrl,
        #[Config('archive.ai_service.timeout_seconds')] private int $timeoutSeconds = 10,
    ) {}

    /**
     * Never throws: an unreachable or unhealthy service is reported in the result.
     *
     * @return array{reachable: bool, status: string|null, version: string|null, gpu: bool|null, latency_ms: int|null, error: string|null}
     */
    public function health(): array
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->send('GET', '/health')->throw();
        } catch (ConnectionException) {
            return $this->unreachable('The AI service could not be reached.');
        } catch (RequestException $exception) {
            return $this->unreachable("The AI service answered with HTTP {$exception->response->status()}.");
        }

        return [
            'reachable' => true,
            'status' => is_string($response->json('status')) ? $response->json('status') : null,
            'version' => is_string($response->json('version')) ? $response->json('version') : null,
            'gpu' => is_bool($response->json('gpu')) ? $response->json('gpu') : null,
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'error' => null,
        ];
    }

    /**
     * Ask the AI service to call Laravel back on the signed /internal/ping
     * route from its Celery worker. Returns the worker job id.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function ping(string $pingId): string
    {
        return (string) $this->send('POST', '/ping', ['ping_id' => $pingId])->throw()->json('job_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    private function send(string $method, string $path, array $payload = []): Response
    {
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $request = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            ->connectTimeout(3)
            ->acceptJson()
            ->withHeaders($this->signature->headers($body));

        if ($method !== 'GET') {
            $request = $request->withBody($body, 'application/json');
        }

        return $request->send($method, $path);
    }

    /**
     * @return array{reachable: false, status: null, version: null, gpu: null, latency_ms: null, error: string}
     */
    private function unreachable(string $error): array
    {
        return [
            'reachable' => false,
            'status' => null,
            'version' => null,
            'gpu' => null,
            'latency_ms' => null,
            'error' => $error,
        ];
    }
}
