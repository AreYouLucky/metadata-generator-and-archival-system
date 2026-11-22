<?php

namespace Tests\Feature\Encoder;

use App\Enums\Preset;
use App\Events\RecordDescriptiveSuggested;
use App\Jobs\SuggestDescriptiveJob;
use App\Models\Genre;
use App\Models\Project;
use App\Models\Record;
use App\Models\User;
use App\Models\VideoType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Fill with AI" in Archive → Descriptive: suggestions written from the
 * active generation's summary, never saved by themselves.
 */
class DescriptiveSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_are_queued_once_at_a_time(): void
    {
        Queue::fake([SuggestDescriptiveJob::class]);
        $record = $this->recordWithMetadata();

        $this->actingAs($record->creator)->postJson(route('records.descriptive-suggestions.store', $record))->assertAccepted();
        $this->actingAs($record->creator)->postJson(route('records.descriptive-suggestions.store', $record))->assertConflict();

        Queue::assertPushed(SuggestDescriptiveJob::class, 1);
    }

    public function test_a_record_without_ai_metadata_or_another_encoder_is_refused(): void
    {
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.descriptive-suggestions.store', $record))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Generate the AI metadata first: the suggestions are written from its summary.');
        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.descriptive-suggestions.store', $this->recordWithMetadata()))
            ->assertForbidden();
    }

    public function test_the_job_sends_the_brief_and_context_and_matches_names_to_ids_without_saving(): void
    {
        Event::fake([RecordDescriptiveSuggested::class]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/generate/descriptive' => Http::response([
            'model' => 'gpt-6.2-sol',
            'fields' => [
                'title' => 'Opening of the Regional S&T Fair 2026',
                'alternative_title' => null,
                'description' => 'Officials open the fair.',
                'genre' => 'Event Coverage',
                'video_type' => 'Full Program',
                'country_origin' => 'PH',
                'date_recorded' => '2026-07-10',
                'date_recorded_precision' => 'day',
            ],
            'usage' => [['kind' => 'llm_input_tokens', 'quantity' => 400, 'estimated_cost' => 0.0008]],
        ])]);
        $genre = Genre::factory()->create(['name' => 'Event Coverage']);
        $type = VideoType::factory()->create(['name' => 'Full Program']);
        $record = $this->recordWithMetadata();
        $record->project()->associate(Project::factory()->create(['title' => 'Regional S&T Fair 2026', 'date' => '2026-07-10']))->save();

        SuggestDescriptiveJob::dispatchSync($record);

        Http::assertSent(fn (Request $request): bool => $request['summary'] === 'Officials open the fair.'
            && $request['project']['date'] === '2026-07-10'
            && in_array('Event Coverage', $request['genres'], true)
            && $request['current_title'] === 'MVI_0042');
        Event::assertDispatched(RecordDescriptiveSuggested::class, fn (RecordDescriptiveSuggested $event): bool => $event->fields['genre_id'] === $genre->id
            && $event->fields['video_type_id'] === $type->id
            && $event->fields['title'] === 'Opening of the Regional S&T Fair 2026');
        $this->assertSame('MVI_0042', $record->refresh()->title);
        $this->assertSame(1, $record->usageRecords()->count());
    }

    private function recordWithMetadata(): Record
    {
        $record = Record::factory()->create(['title' => 'MVI_0042']);
        $record->metadata()->create([
            'preset' => Preset::Generic,
            'model' => 'gpt-6.2-sol',
            'is_active' => true,
            'payload' => ['summary' => 'Officials open the fair.', 'chapters' => [['start' => 0, 'title' => 'Opening']], 'keywords' => ['DOST']],
        ]);

        return $record;
    }
}
