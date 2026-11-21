<?php

namespace Tests\Feature\Encoder;

use App\Enums\TaskStatus;
use App\Events\RecordTranslationUpdated;
use App\Jobs\StartTranslationJob;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\RecordTranscript;
use App\Models\User;
use App\Services\InternalSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Translations end to end: the request, the job's call to the AI service and
 * the signed callbacks that store the lines.
 */
class TranslationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_translation_is_queued_and_handed_to_the_ai_service(): void
    {
        Queue::fake([StartTranslationJob::class]);
        [$record] = $this->recordWithTranscript();

        $this->actingAs($record->creator)
            ->postJson(route('records.translations.store', $record), ['language' => 'fil'])
            ->assertAccepted()
            ->assertJsonPath('translation.language', 'fil')
            ->assertJsonPath('translation.status', 'queued');

        Queue::assertPushed(StartTranslationJob::class, fn (StartTranslationJob $job): bool => $job->translation->language === 'fil');
        $this->assertDatabaseHas('record_transcripts', ['record_id' => $record->id, 'language' => 'fil', 'is_original' => false]);
    }

    public function test_the_job_sends_only_line_ids_and_text_with_the_language_name(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/translate' => Http::response(['job_id' => 'j-1'], 202)]);
        [$record, $lines] = $this->recordWithTranscript();
        $translation = RecordTranscript::factory()->for($record)->translation('ceb')->create(['status' => TaskStatus::Queued]);

        StartTranslationJob::dispatchSync($record, $translation);

        Http::assertSent(fn (Request $request): bool => $request['transcript_id'] === $translation->id
            && $request['source_language'] === 'en'
            && $request['target_language_name'] === 'Cebuano'
            && $request['lines'] === [['id' => $lines[0]->id, 'text' => 'Welcome.'], ['id' => $lines[1]->id, 'text' => 'Thank you.']]);
    }

    public function test_the_original_language_and_unknown_languages_are_refused(): void
    {
        [$record] = $this->recordWithTranscript();

        $this->actingAs($record->creator)
            ->postJson(route('records.translations.store', $record), ['language' => 'en'])
            ->assertJsonValidationErrors(['language' => 'The transcript is already in that language.']);
        $this->actingAs($record->creator)
            ->postJson(route('records.translations.store', $record), ['language' => 'xx'])
            ->assertJsonValidationErrors(['language']);
    }

    public function test_a_running_translation_cannot_be_started_again(): void
    {
        Queue::fake([StartTranslationJob::class]);
        [$record] = $this->recordWithTranscript();
        RecordTranscript::factory()->for($record)->translation('fil')->create(['status' => TaskStatus::Processing]);

        $this->actingAs($record->creator)
            ->postJson(route('records.translations.store', $record), ['language' => 'fil'])
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_another_encoder_cannot_translate_or_read_a_translation(): void
    {
        [$record] = $this->recordWithTranscript();
        RecordTranscript::factory()->for($record)->translation('fil')->create();
        $intruder = User::factory()->encoder()->create();

        $this->actingAs($intruder)->postJson(route('records.translations.store', $record), ['language' => 'fil'])->assertForbidden();
        $this->actingAs($intruder)->getJson(route('records.translations.show', [$record, 'fil']))->assertForbidden();
    }

    public function test_the_result_takes_timings_and_speakers_from_the_original_lines(): void
    {
        Event::fake([RecordTranslationUpdated::class]);
        [$record, $lines] = $this->recordWithTranscript();
        $translation = RecordTranscript::factory()->for($record)->translation('fil')->create(['status' => TaskStatus::Processing]);

        $this->postSigned("/internal/records/{$record->id}/translations/{$translation->id}/result", [
            'model' => 'gpt-6.2-sol',
            'kept' => 0,
            'lines' => [
                ['id' => $lines[0]->id, 'text' => 'Maligayang pagdating.'],
                ['id' => $lines[1]->id, 'text' => 'Salamat.'],
                ['id' => 999999, 'text' => 'A line that was deleted meanwhile.'],
            ],
            'usage' => [
                ['kind' => 'llm_input_tokens', 'quantity' => 100, 'estimated_cost' => 0.0002],
                ['kind' => 'llm_input_tokens', 'quantity' => 50, 'estimated_cost' => 0.0001],
                ['kind' => 'llm_output_tokens', 'quantity' => 80, 'estimated_cost' => 0.0008],
            ],
        ])->assertOk();

        $this->assertSame(TaskStatus::Done, $translation->refresh()->status);
        $stored = $translation->segments()->get();
        $this->assertSame(['Maligayang pagdating.', 'Salamat.'], $stored->pluck('text')->all());
        $this->assertSame([$lines[1]->start, $lines[1]->end, $lines[1]->record_speaker_id], [$stored[1]->start, $stored[1]->end, $stored[1]->record_speaker_id]);
        $this->assertSame([150.0, 80.0], $record->usageRecords()->orderBy('kind')->pluck('quantity')->map(fn ($quantity): float => (float) $quantity)->all());
        Event::assertDispatched(RecordTranslationUpdated::class, fn (RecordTranslationUpdated $event): bool => $event->translation->status === TaskStatus::Done);

        $this->actingAs($record->creator)
            ->getJson(route('records.translations.show', [$record, 'fil']))
            ->assertOk()
            ->assertJsonPath('segments.0.text', 'Maligayang pagdating.');
    }

    public function test_a_translated_line_can_be_corrected_and_is_marked_edited(): void
    {
        [$record] = $this->recordWithTranscript();
        $translation = RecordTranscript::factory()->for($record)->translation('fil')->create();
        $line = RecordSegment::factory()->for($translation, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 2.5, 'text' => 'Maligayng pagdating.', 'original_text' => 'Maligayng pagdating.']);

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $line]), ['text' => 'Maligayang pagdating.'])
            ->assertOk()
            ->assertJsonPath('is_edited', true);

        $this->assertSame('Maligayng pagdating.', $line->refresh()->original_text);
        $this->actingAs($record->creator)
            ->getJson(route('records.translations.show', [$record, 'fil']))
            ->assertJsonPath('segments.0.text', 'Maligayang pagdating.')
            ->assertJsonPath('segments.0.is_edited', true);
        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn ($page) => $page->where('translations.0.edited_count', 1));
    }

    public function test_another_records_translated_line_cannot_be_edited_through_this_record(): void
    {
        [$record] = $this->recordWithTranscript();
        $foreign = RecordSegment::factory()->for(RecordTranscript::factory()->translation('fil'), 'transcript')->create();

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $foreign]), ['text' => 'Hijacked.'])
            ->assertNotFound();
    }

    public function test_a_failure_is_stored_with_its_message(): void
    {
        [$record] = $this->recordWithTranscript();
        $translation = RecordTranscript::factory()->for($record)->translation('fil')->create(['status' => TaskStatus::Processing]);

        $this->postSigned("/internal/records/{$record->id}/translations/{$translation->id}/progress", [
            'status' => 'failed',
            'progress' => 0,
            'message' => "OpenAI's rate limit or quota was reached. Retry later.",
        ])->assertOk();

        $this->assertSame([TaskStatus::Failed, "OpenAI's rate limit or quota was reached. Retry later."], [$translation->refresh()->status, $translation->error]);
    }

    public function test_callbacks_cannot_overwrite_the_original_or_another_records_transcript(): void
    {
        [$record] = $this->recordWithTranscript();
        $original = $record->originalTranscript()->sole();
        $otherTranslation = RecordTranscript::factory()->translation('fil')->create();
        $payload = ['model' => 'm', 'kept' => 0, 'lines' => [], 'usage' => []];

        $this->postSigned("/internal/records/{$record->id}/translations/{$original->id}/result", $payload)->assertNotFound();
        // A different body: the replay guard refuses an identical signed body sent twice.
        $this->postSigned("/internal/records/{$record->id}/translations/{$otherTranslation->id}/result", [...$payload, 'kept' => 1])->assertNotFound();
        $this->assertSame(2, $original->segments()->count());
    }

    /**
     * @return array{0: Record, 1: list<RecordSegment>}
     */
    private function recordWithTranscript(): array
    {
        $record = Record::factory()->create(['language' => 'en']);
        $speaker = RecordSpeaker::factory()->for($record)->create(['label' => 'S1']);
        $transcript = RecordTranscript::factory()->for($record)->create(['language' => 'en']);
        $lines = [
            RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 2.5, 'text' => 'Welcome.']),
            RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 2, 'start' => 2.5, 'end' => 4, 'text' => 'Thank you.', 'record_speaker_id' => $speaker->id]),
        ];

        return [$record, $lines];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(string $uri, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars([
            ...app(InternalSignature::class)->headers($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }
}
