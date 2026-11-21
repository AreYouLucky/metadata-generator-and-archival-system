<?php

namespace Tests\Feature\Encoder;

use App\Http\Requests\Encoder\StoreSpeakerRequest;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpeakerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_speaker_takes_the_next_free_label(): void
    {
        $record = Record::factory()->create();
        RecordSpeaker::factory()->for($record)->create(['label' => 'S1']);
        RecordSpeaker::factory()->for($record)->create(['label' => 'S3']);

        $this->actingAs($record->creator)
            ->postJson(route('records.speakers.store', $record), ['display_name' => '  Host  '])
            ->assertCreated()
            ->assertJson(['label' => 'S4', 'display_name' => 'Host']);

        $this->actingAs($record->creator)
            ->postJson(route('records.speakers.store', $record), ['display_name' => ''])
            ->assertCreated()
            ->assertJson(['label' => 'S5', 'display_name' => null]);
    }

    public function test_a_speaker_can_be_renamed_and_a_blank_name_falls_back_to_the_label(): void
    {
        $speaker = RecordSpeaker::factory()->create(['label' => 'S1']);
        $record = $speaker->record;

        $this->actingAs($record->creator)
            ->patchJson(route('records.speakers.update', [$record, $speaker]), ['display_name' => 'Secretary Cruz'])
            ->assertOk()
            ->assertJson(['label' => 'S1', 'display_name' => 'Secretary Cruz']);

        $this->actingAs($record->creator)
            ->patchJson(route('records.speakers.update', [$record, $speaker]), ['display_name' => ' '])
            ->assertJson(['display_name' => null]);

        $this->assertNull($speaker->refresh()->display_name);
    }

    public function test_a_speaker_of_another_record_is_not_found(): void
    {
        $record = Record::factory()->create();
        $foreignSpeaker = RecordSpeaker::factory()->for(Record::factory()->for($record->creator, 'creator'))->create();

        $this->actingAs($record->creator)
            ->patchJson(route('records.speakers.update', [$record, $foreignSpeaker]), ['display_name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertNull($foreignSpeaker->refresh()->display_name);
    }

    public function test_another_encoder_cannot_add_or_rename_speakers(): void
    {
        $speaker = RecordSpeaker::factory()->create();
        $record = $speaker->record;
        $stranger = User::factory()->encoder()->create();

        $this->actingAs($stranger)
            ->postJson(route('records.speakers.store', $record), ['display_name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->patchJson(route('records.speakers.update', [$record, $speaker]), ['display_name' => 'Nope'])
            ->assertForbidden();

        $this->assertSame(1, $record->speakers()->count());
        $this->assertNull($speaker->refresh()->display_name);
    }

    public function test_a_record_cannot_have_more_than_the_speaker_limit(): void
    {
        $record = Record::factory()->create();

        foreach (range(1, StoreSpeakerRequest::MAX_SPEAKERS) as $number) {
            RecordSpeaker::factory()->for($record)->create(['label' => 'S'.$number]);
        }

        $this->actingAs($record->creator)
            ->postJson(route('records.speakers.store', $record), ['display_name' => 'One more'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name' => 'A record can have at most 50 speakers.']);
    }

    public function test_the_workspace_lists_the_speakers_and_the_speaker_of_each_line(): void
    {
        $record = Record::factory()->create();
        $host = RecordSpeaker::factory()->for($record)->create(['label' => 'S1', 'display_name' => 'Host']);
        $transcript = RecordTranscript::factory()->for($record)->create();
        RecordSegment::factory()->for($transcript, 'transcript')->create(['record_speaker_id' => $host->id]);

        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->where('speakers', [['id' => $host->id, 'label' => 'S1', 'display_name' => 'Host']])
                ->where('transcript.segments.0.speaker_id', $host->id));
    }
}
