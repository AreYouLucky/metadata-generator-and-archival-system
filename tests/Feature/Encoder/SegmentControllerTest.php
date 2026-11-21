<?php

namespace Tests\Feature\Encoder;

use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_edit_keeps_the_original_text_and_marks_the_line_edited(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Helo world');
        $record = $segment->transcript->record;

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $segment]), ['text' => ' Hello world '])
            ->assertOk()
            ->assertJson(['text' => 'Hello world', 'is_edited' => true]);

        $segment->refresh();
        $this->assertSame('Hello world', $segment->text);
        $this->assertSame('Helo world', $segment->original_text);
    }

    public function test_restoring_the_original_text_clears_the_edited_flag(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Original');
        $segment->update(['text' => 'Changed', 'is_edited' => true]);
        $record = $segment->transcript->record;

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $segment]), ['text' => 'Original'])
            ->assertJson(['is_edited' => false]);
    }

    public function test_a_segment_from_another_record_is_not_found(): void
    {
        $record = Record::factory()->create();
        $foreignSegment = $this->segmentOf(Record::factory()->for($record->creator, 'creator')->create(), 'Elsewhere');

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $foreignSegment]), ['text' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Elsewhere', $foreignSegment->refresh()->text);
    }

    public function test_another_encoder_and_a_submitted_record_cannot_be_edited(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Line');
        $record = $segment->transcript->record;

        $this->actingAs(User::factory()->encoder()->create())
            ->patchJson(route('records.segments.update', [$record, $segment]), ['text' => 'Nope'])
            ->assertForbidden();

        $submitted = Record::factory()->submitted()->create();
        $submittedSegment = $this->segmentOf($submitted, 'Line');

        $this->actingAs($submitted->creator)
            ->patchJson(route('records.segments.update', [$submitted, $submittedSegment]), ['text' => 'Nope'])
            ->assertForbidden();
    }

    public function test_an_empty_line_is_rejected(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Line');
        $record = $segment->transcript->record;

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $segment]), ['text' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['text']);
    }

    public function test_a_line_can_be_given_a_speaker_and_have_it_cleared(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Line');
        $record = $segment->transcript->record;
        $host = RecordSpeaker::factory()->for($record)->create(['label' => 'S1']);

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $segment]), ['speaker_id' => $host->id])
            ->assertOk()
            ->assertJson(['speaker_id' => $host->id, 'text' => 'Line', 'is_edited' => false]);

        $this->assertSame($host->id, $segment->refresh()->record_speaker_id);

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $segment]), ['speaker_id' => null])
            ->assertJson(['speaker_id' => null]);

        $this->assertNull($segment->refresh()->record_speaker_id);
    }

    public function test_a_speaker_from_another_record_is_rejected(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Line');
        $record = $segment->transcript->record;
        $foreignSpeaker = RecordSpeaker::factory()->create();

        $this->actingAs($record->creator)
            ->patchJson(route('records.segments.update', [$record, $segment]), ['speaker_id' => $foreignSpeaker->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['speaker_id' => 'The selected speaker id is invalid.']);

        $this->assertNull($segment->refresh()->record_speaker_id);
    }

    public function test_a_speaker_can_be_applied_to_many_lines_at_once(): void
    {
        $first = $this->segmentOf(Record::factory()->create(), 'One');
        $record = $first->transcript->record;
        $second = RecordSegment::factory()->for($first->transcript, 'transcript')->create();
        $untouched = RecordSegment::factory()->for($first->transcript, 'transcript')->create();
        $host = RecordSpeaker::factory()->for($record)->create(['label' => 'S1']);

        $this->actingAs($record->creator)
            ->patchJson(route('records.transcript.speaker', $record), [
                'segment_ids' => [$first->id, $second->id],
                'speaker_id' => $host->id,
            ])
            ->assertOk()
            ->assertExactJson(['updated' => 2]);

        $this->assertSame($host->id, $first->refresh()->record_speaker_id);
        $this->assertSame($host->id, $second->refresh()->record_speaker_id);
        $this->assertNull($untouched->refresh()->record_speaker_id);
    }

    public function test_applying_a_speaker_never_touches_another_records_lines(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Mine');
        $record = $segment->transcript->record;
        $foreignSegment = $this->segmentOf(Record::factory()->for($record->creator, 'creator')->create(), 'Elsewhere');
        $host = RecordSpeaker::factory()->for($record)->create(['label' => 'S1']);

        $this->actingAs($record->creator)
            ->patchJson(route('records.transcript.speaker', $record), [
                'segment_ids' => [$segment->id, $foreignSegment->id],
                'speaker_id' => $host->id,
            ])
            ->assertExactJson(['updated' => 1]);

        $this->assertNull($foreignSegment->refresh()->record_speaker_id);
    }

    public function test_applying_a_speaker_is_refused_for_another_encoder_and_a_foreign_speaker(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Line');
        $record = $segment->transcript->record;
        $payload = ['segment_ids' => [$segment->id], 'speaker_id' => RecordSpeaker::factory()->create()->id];

        $this->actingAs($record->creator)
            ->patchJson(route('records.transcript.speaker', $record), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['speaker_id' => 'The selected speaker id is invalid.']);

        $this->actingAs(User::factory()->encoder()->create())
            ->patchJson(route('records.transcript.speaker', $record), [...$payload, 'speaker_id' => null])
            ->assertForbidden();

        $this->assertNull($segment->refresh()->record_speaker_id);
    }

    public function test_a_line_can_be_deleted(): void
    {
        $segment = $this->segmentOf(Record::factory()->create(), 'Music playing');
        $record = $segment->transcript->record;

        $this->actingAs($record->creator)
            ->deleteJson(route('records.segments.destroy', [$record, $segment]))
            ->assertNoContent();

        $this->assertModelMissing($segment);
    }

    public function test_a_line_of_another_record_or_another_encoder_cannot_be_deleted(): void
    {
        $record = Record::factory()->create();
        $foreignSegment = $this->segmentOf(Record::factory()->for($record->creator, 'creator')->create(), 'Elsewhere');

        $this->actingAs($record->creator)
            ->deleteJson(route('records.segments.destroy', [$record, $foreignSegment]))
            ->assertNotFound();

        $segment = $this->segmentOf($record, 'Mine');

        $this->actingAs(User::factory()->encoder()->create())
            ->deleteJson(route('records.segments.destroy', [$record, $segment]))
            ->assertForbidden();

        $this->assertModelExists($foreignSegment);
        $this->assertModelExists($segment);
    }

    private function segmentOf(Record $record, string $text): RecordSegment
    {
        $transcript = RecordTranscript::factory()->for($record)->create();

        return RecordSegment::factory()->for($transcript, 'transcript')->create(['text' => $text, 'original_text' => $text]);
    }
}
