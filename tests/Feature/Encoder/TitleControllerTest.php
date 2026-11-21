<?php

namespace Tests\Feature\Encoder;

use App\Enums\Preset;
use App\Events\RecordTitlesGenerated;
use App\Jobs\GenerateTitlesJob;
use App\Models\Record;
use App\Models\RecordMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Generate more" titles: the request, the lock against double clicks, and
 * the job that appends the new options to the generation.
 */
class TitleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_titles_are_queued_for_the_active_generation(): void
    {
        Queue::fake([GenerateTitlesJob::class]);
        $metadata = $this->activeMetadata();

        $this->actingAs($metadata->record->creator)
            ->postJson(route('records.titles.store', $metadata->record), ['metadata_id' => $metadata->id])
            ->assertAccepted();

        Queue::assertPushed(GenerateTitlesJob::class, fn (GenerateTitlesJob $job): bool => $job->metadataId === $metadata->id);
    }

    public function test_a_second_request_while_one_runs_is_refused(): void
    {
        Queue::fake([GenerateTitlesJob::class]);
        $metadata = $this->activeMetadata();
        Cache::add(GenerateTitlesJob::lockKey($metadata->record), true, 60);

        $this->actingAs($metadata->record->creator)
            ->postJson(route('records.titles.store', $metadata->record), ['metadata_id' => $metadata->id])
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_titles_for_a_generation_that_is_no_longer_active_are_refused(): void
    {
        $metadata = $this->activeMetadata();

        $this->actingAs($metadata->record->creator)
            ->postJson(route('records.titles.store', $metadata->record), ['metadata_id' => $metadata->id + 1])
            ->assertConflict();
    }

    public function test_another_encoder_cannot_generate_titles(): void
    {
        $metadata = $this->activeMetadata();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.titles.store', $metadata->record), ['metadata_id' => $metadata->id])
            ->assertForbidden();
    }

    public function test_the_job_appends_only_new_titles_and_broadcasts_them(): void
    {
        Event::fake([RecordTitlesGenerated::class]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/generate/titles' => Http::response([
            'model' => 'gpt-6.2-sol',
            'titles' => ['opening of the fair', 'Science for every barangay'],
            'usage' => [['kind' => 'llm_input_tokens', 'quantity' => 300, 'estimated_cost' => 0.0006]],
        ])]);
        $metadata = $this->activeMetadata();
        Cache::add(GenerateTitlesJob::lockKey($metadata->record), true, 60);

        GenerateTitlesJob::dispatchSync($metadata->record, $metadata->id);

        Http::assertSent(fn (Request $request): bool => $request['transcript_summary'] === 'Officials open the fair.'
            && $request['avoid'] === ['Opening of the fair']
            && $request['preset']['name'] === 'tiktok');
        $this->assertSame(['Opening of the fair', 'Science for every barangay'], $metadata->refresh()->payload['titles']);
        $this->assertSame(1, $metadata->record->usageRecords()->count());
        $this->assertFalse(Cache::has(GenerateTitlesJob::lockKey($metadata->record)));
        Event::assertDispatched(RecordTitlesGenerated::class, fn (RecordTitlesGenerated $event): bool => $event->titles === ['Science for every barangay'] && $event->error === null);
    }

    public function test_a_provider_error_is_broadcast_with_the_services_explanation(): void
    {
        Event::fake([RecordTitlesGenerated::class]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/generate/titles' => Http::response(['detail' => 'OpenAI rejected the API key.'], 502)]);
        $metadata = $this->activeMetadata();

        GenerateTitlesJob::dispatchSync($metadata->record, $metadata->id);

        Event::assertDispatched(RecordTitlesGenerated::class, fn (RecordTitlesGenerated $event): bool => $event->error === 'OpenAI rejected the API key.');
        $this->assertSame(['Opening of the fair'], $metadata->refresh()->payload['titles']);
    }

    private function activeMetadata(): RecordMetadata
    {
        return Record::factory()->create()->metadata()->create([
            'preset' => Preset::Tiktok,
            'model' => 'gpt-6.2-sol',
            'is_active' => true,
            'payload' => ['titles' => ['Opening of the fair'], 'summary' => 'Officials open the fair.'],
        ]);
    }
}
