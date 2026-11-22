<?php

namespace Tests\Feature\Jobs;

use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use App\Jobs\SendRecordWebhookJob;
use App\Models\Record;
use App\Services\Records\PipelineRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Public-API webhooks: queued when an API record finishes processing,
 * signed with the owner's secret, never sent to a private address.
 */
class SendRecordWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private const string WEBHOOK = 'https://93.184.215.14/hooks/archive';

    public function test_a_record_with_a_webhook_queues_it_when_processing_finishes(): void
    {
        $record = $this->recordWithWebhook(self::WEBHOOK);
        $record->stages()->create(['stage' => PipelineStage::Transcribe, 'status' => StageStatus::Done]);
        Queue::fake([SendRecordWebhookJob::class]);

        app(PipelineRecorder::class)->refreshStatus($record);

        Queue::assertPushed(SendRecordWebhookJob::class, fn (SendRecordWebhookJob $job): bool => $job->record->is($record) && $job->event === 'record.ready');
    }

    public function test_the_webhook_is_signed_with_the_owners_secret(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => Http::response('', 204)]);
        $record = $this->recordWithWebhook(self::WEBHOOK);

        (new SendRecordWebhookJob($record, 'record.ready'))->handle();

        Http::assertSent(function (Request $request) use ($record): bool {
            $timestamp = $request->header('X-Archive-Timestamp')[0];

            return $request->url() === self::WEBHOOK
                && $request['event'] === 'record.ready'
                && $request['record']['id'] === $record->id
                && $timestamp === (string) now()->getTimestamp()
                && $request->header('X-Archive-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'whsec_test');
        });
        $this->assertNotNull($record->refresh()->webhook_sent_at);
    }

    public function test_a_webhook_to_a_private_address_is_never_sent(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $record = $this->recordWithWebhook('https://10.1.2.3/hook');

        (new SendRecordWebhookJob($record, 'record.ready'))->handle();

        Http::assertNothingSent();
        $this->assertNull($record->refresh()->webhook_sent_at);
    }

    public function test_a_server_error_is_retried(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => Http::response('', 503)]);
        $record = $this->recordWithWebhook(self::WEBHOOK);

        $this->expectException(RuntimeException::class);

        (new SendRecordWebhookJob($record, 'record.ready'))->handle();
    }

    private function recordWithWebhook(string $url): Record
    {
        $record = Record::factory()->create();
        $record->webhook_url = $url;
        $record->save();
        $record->creator->webhook_secret = 'whsec_test';
        $record->creator->save();

        return $record;
    }
}
