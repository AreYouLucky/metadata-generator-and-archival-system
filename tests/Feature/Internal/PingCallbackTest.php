<?php

namespace Tests\Feature\Internal;

use App\Enums\PingSource;
use App\Events\SystemPingReceived;
use App\Services\InternalSignature;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PingCallbackTest extends TestCase
{
    private const string PING_ID = '0b5a5a3e-7c1e-4d3a-9f0e-2a6f4b8c9d10';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([SystemPingReceived::class]);
    }

    public function test_a_signed_callback_is_accepted_and_broadcast_to_admins(): void
    {
        $this->postSigned(['ping_id' => self::PING_ID, 'source' => 'ai-worker'])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        Event::assertDispatched(
            SystemPingReceived::class,
            fn (SystemPingReceived $event): bool => $event->pingId === self::PING_ID && $event->source === PingSource::AiWorker,
        );
    }

    public function test_an_unsigned_callback_is_rejected(): void
    {
        $this->postJson('/internal/ping', ['ping_id' => self::PING_ID, 'source' => 'ai-worker'])
            ->assertUnauthorized();

        Event::assertNotDispatched(SystemPingReceived::class);
    }

    public function test_a_body_changed_after_signing_is_rejected(): void
    {
        $body = json_encode(['ping_id' => self::PING_ID, 'source' => 'ai-worker'], JSON_THROW_ON_ERROR);
        $headers = app(InternalSignature::class)->headers($body);

        $this->sendRaw(str_replace('ai-worker', 'laravel', $body), $headers)->assertUnauthorized();

        Event::assertNotDispatched(SystemPingReceived::class);
    }

    public function test_a_replayed_callback_is_rejected(): void
    {
        $body = json_encode(['ping_id' => self::PING_ID, 'source' => 'ai-worker'], JSON_THROW_ON_ERROR);
        $headers = app(InternalSignature::class)->headers($body);

        $this->sendRaw($body, $headers)->assertOk();
        $this->sendRaw($body, $headers)->assertUnauthorized();

        Event::assertDispatchedTimes(SystemPingReceived::class, 1);
    }

    public function test_a_signed_callback_still_has_its_input_validated(): void
    {
        $this->postSigned(['ping_id' => 'not-a-uuid', 'source' => 'laravel'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ping_id', 'source']);

        Event::assertNotDispatched(SystemPingReceived::class);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function postSigned(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->sendRaw($body, app(InternalSignature::class)->headers($body));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function sendRaw(string $body, array $headers): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/internal/ping', server: $server, content: $body);
    }
}
