<?php

namespace Tests\Feature\Services;

use App\Services\AiService\AiServiceClient;
use App\Services\InternalSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceClientTest extends TestCase
{
    public function test_health_sends_a_signed_request_and_reports_the_service_status(): void
    {
        Http::fake(['ai-api.test/health' => Http::response(['status' => 'ok', 'version' => '0.1.0', 'gpu' => false])]);

        $health = app(AiServiceClient::class)->health();

        $this->assertTrue($health['reachable']);
        $this->assertSame('ok', $health['status']);
        $this->assertSame('0.1.0', $health['version']);
        $this->assertFalse($health['gpu']);
        $this->assertNull($health['error']);
        Http::assertSent(fn (Request $request): bool => $this->isValidlySigned($request));
    }

    public function test_health_reports_an_unreachable_service_instead_of_throwing(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $health = app(AiServiceClient::class)->health();

        $this->assertFalse($health['reachable']);
        $this->assertSame('The AI service could not be reached.', $health['error']);
    }

    public function test_health_reports_an_error_response_as_unreachable(): void
    {
        Http::fake(['ai-api.test/health' => Http::response(['detail' => 'boom'], 500)]);

        $health = app(AiServiceClient::class)->health();

        $this->assertFalse($health['reachable']);
        $this->assertSame('The AI service answered with HTTP 500.', $health['error']);
    }

    public function test_ping_posts_the_ping_id_with_a_signature_of_the_exact_body(): void
    {
        Http::fake(['ai-api.test/ping' => Http::response(['job_id' => 'celery-123'], 202)]);

        $jobId = app(AiServiceClient::class)->ping('0b5a5a3e-7c1e-4d3a-9f0e-2a6f4b8c9d10');

        $this->assertSame('celery-123', $jobId);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['ping_id'] === '0b5a5a3e-7c1e-4d3a-9f0e-2a6f4b8c9d10'
            && $this->isValidlySigned($request));
    }

    private function isValidlySigned(Request $request): bool
    {
        return app(InternalSignature::class)->verify(
            $request->header('X-Timestamp')[0] ?? null,
            $request->header('X-Signature')[0] ?? null,
            $request->body(),
        );
    }
}
