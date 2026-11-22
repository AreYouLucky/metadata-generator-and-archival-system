<?php

namespace Tests\Feature\Services;

use App\Services\AiService\AiProviders;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProvidersTest extends TestCase
{
    public function test_the_providers_are_read_from_health_and_kept(): void
    {
        Http::fake(['ai-api.test/health' => Http::response(['status' => 'ok', 'version' => '0.4.0', 'gpu' => true, 'transcription_provider' => 'assemblyai', 'diarization_provider' => 'pyannote'])]);

        $first = app(AiProviders::class)->current();
        $second = app(AiProviders::class)->current();

        $this->assertSame(['transcription' => 'assemblyai', 'diarization' => 'pyannote'], $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_an_unreachable_service_gives_unknown_providers_and_is_asked_again(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts): never {
            $attempts++;

            throw new ConnectionException('Connection refused');
        });

        $this->assertSame(['transcription' => null, 'diarization' => null], app(AiProviders::class)->current());
        app(AiProviders::class)->current();

        $this->assertSame(2, $attempts);
    }
}
