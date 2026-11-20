<?php

namespace Tests\Feature\Models;

use App\Enums\ProcessingStatus;
use App\Enums\SecurityClassification;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_record_starts_queued_unclassified_and_unapproved_before_it_is_refreshed(): void
    {
        $encoder = User::factory()->encoder()->create();

        $record = $encoder->records()->create(['title' => 'Regional S&T fair opening']);

        $this->assertSame(ProcessingStatus::Queued, $record->processing_status);
        $this->assertSame(SecurityClassification::Unclassified, $record->security_classification);
        $this->assertFalse($record->is_approved);
    }

    public function test_approval_and_processing_fields_cannot_be_mass_assigned(): void
    {
        $encoder = User::factory()->encoder()->create();

        $record = $encoder->records()->create([
            'title' => 'Regional S&T fair opening',
            'is_approved' => true,
            'processing_status' => ProcessingStatus::Ready,
            'storage_location' => 'public/leaked.mp4',
        ]);

        $record->refresh();
        $this->assertFalse($record->is_approved);
        $this->assertSame(ProcessingStatus::Queued, $record->processing_status);
        $this->assertNull($record->storage_location);
    }

    public function test_original_transcript_ignores_translations(): void
    {
        $record = Record::factory()->create();
        RecordTranscript::factory()->for($record)->create(['language' => 'fil', 'is_original' => false]);
        $original = RecordTranscript::factory()->for($record)->create(['language' => 'en', 'is_original' => true]);

        $this->assertTrue($record->originalTranscript?->is($original));
    }

    public function test_permanently_deleting_a_record_removes_its_transcripts_and_segments(): void
    {
        $record = Record::factory()->create();
        $transcript = RecordTranscript::factory()->for($record)->create();
        $segment = RecordSegment::factory()->for($transcript, 'transcript')->create();

        $record->forceDelete();

        $this->assertModelMissing($transcript);
        $this->assertModelMissing($segment);
    }
}
