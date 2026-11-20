<?php

namespace Tests\Feature\Models;

use App\Enums\FileRole;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Enums\SecurityClassification;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_record_starts_as_a_queued_unclassified_draft_before_it_is_refreshed(): void
    {
        $encoder = User::factory()->encoder()->create();

        $record = $encoder->records()->create(['title' => 'Regional S&T fair opening']);

        $this->assertSame(ProcessingStatus::Queued, $record->processing_status);
        $this->assertSame(SecurityClassification::Unclassified, $record->security_classification);
        $this->assertSame(ReviewStatus::Draft, $record->review_status);
    }

    public function test_workflow_fields_cannot_be_mass_assigned(): void
    {
        $encoder = User::factory()->encoder()->create();

        $record = $encoder->records()->create([
            'title' => 'Regional S&T fair opening',
            'review_status' => ReviewStatus::Approved,
            'published_at' => now(),
            'processing_status' => ProcessingStatus::Ready,
            'accession_number' => 'ACC-2026-99999',
        ]);

        $record->refresh();
        $this->assertSame(ReviewStatus::Draft, $record->review_status);
        $this->assertNull($record->published_at);
        $this->assertSame(ProcessingStatus::Queued, $record->processing_status);
        $this->assertNull($record->accession_number);
    }

    public function test_master_and_proxy_relations_pick_the_file_with_that_role(): void
    {
        $record = Record::factory()->create();
        $master = RecordFile::factory()->for($record)->create(['role' => FileRole::Master]);
        $proxy = RecordFile::factory()->for($record)->create(['role' => FileRole::Proxy]);

        $this->assertTrue($record->masterFile?->is($master));
        $this->assertTrue($record->proxyFile?->is($proxy));
    }

    public function test_original_transcript_ignores_translations(): void
    {
        $record = Record::factory()->create();
        RecordTranscript::factory()->for($record)->create(['language' => 'fil', 'is_original' => false]);
        $original = RecordTranscript::factory()->for($record)->create(['language' => 'en', 'is_original' => true]);

        $this->assertTrue($record->originalTranscript?->is($original));
    }

    public function test_permanently_deleting_a_record_removes_its_files_transcripts_and_segments(): void
    {
        $record = Record::factory()->create();
        $file = RecordFile::factory()->for($record)->create();
        $transcript = RecordTranscript::factory()->for($record)->create();
        $segment = RecordSegment::factory()->for($transcript, 'transcript')->create();

        $record->forceDelete();

        $this->assertModelMissing($file);
        $this->assertModelMissing($transcript);
        $this->assertModelMissing($segment);
    }
}
