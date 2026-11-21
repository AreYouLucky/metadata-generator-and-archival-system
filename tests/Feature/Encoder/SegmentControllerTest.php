<?php

namespace Tests\Feature\Encoder;

use App\Models\Record;
use App\Models\RecordSegment;
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

    private function segmentOf(Record $record, string $text): RecordSegment
    {
        $transcript = RecordTranscript::factory()->for($record)->create();

        return RecordSegment::factory()->for($transcript, 'transcript')->create(['text' => $text, 'original_text' => $text]);
    }
}
