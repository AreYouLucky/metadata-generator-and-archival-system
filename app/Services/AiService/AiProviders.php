<?php

namespace App\Services\AiService;

use Illuminate\Support\Facades\Cache;

/**
 * Which providers the AI service sends audio to, so pages can say when it
 * leaves this organisation's machines (PROJECT_SPEC §14, privacy). Read from
 * /health and kept for ten minutes; unknown (null) while the service is down,
 * which isn't cached, so the next page asks again.
 */
class AiProviders
{
    private const string CACHE_KEY = 'ai-service:providers';

    private const int TTL_SECONDS = 600;

    public function __construct(private AiServiceClient $client) {}

    /**
     * @return array{transcription: string|null, diarization: string|null}
     */
    public function current(): array
    {
        /** @var array{transcription: string|null, diarization: string|null}|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        $health = $this->client->health();
        $providers = [
            'transcription' => $health['transcription_provider'],
            'diarization' => $health['diarization_provider'],
        ];

        if ($health['reachable']) {
            Cache::put(self::CACHE_KEY, $providers, self::TTL_SECONDS);
        }

        return $providers;
    }
}
