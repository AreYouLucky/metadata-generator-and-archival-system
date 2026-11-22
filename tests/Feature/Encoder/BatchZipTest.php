<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\BatchZipStatus;
use App\Enums\ProcessingStatus;
use App\Enums\SecurityClassification;
use App\Jobs\BuildBatchZipJob;
use App\Models\Batch;
use App\Models\Record;
use App\Models\RecordAttachment;
use App\Models\RecordRights;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BatchZipTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_zip_holds_each_permitted_records_exports_and_a_manifest_of_what_was_left_out(): void
    {
        Storage::fake('media');
        $batch = Batch::factory()->create(['name' => 'Town hall']);
        $draft = $this->record($batch, ['title' => 'Opening remarks', 'accession_number' => 'ACC-1']);
        $transcript = RecordTranscript::factory()->for($draft)->create(['language' => 'en']);
        RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 2, 'text' => 'Good morning.']);
        Storage::disk('media')->put("records/{$draft->id}/thumbnail/thumb.jpg", 'jpeg');
        $draft->forceFill(['thumbnail_path' => "records/{$draft->id}/thumbnail/thumb.jpg"])->save();
        // A release form: never part of a ZIP.
        $draft->attachments()->save((new RecordAttachment(['kind' => 'release_form', 'original_filename' => 'consent.pdf']))->forceFill([
            'path' => "records/{$draft->id}/attachments/consent.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 4,
            'sha256_checksum' => str_repeat('a', 64),
        ]));
        Storage::disk('media')->put("records/{$draft->id}/attachments/consent.pdf", '%PDF');
        $classified = $this->record($batch, ['title' => 'Briefing', 'accession_number' => 'ACC-2', 'security_classification' => SecurityClassification::Confidential]);
        $embargoed = $this->record($batch, ['title' => 'Launch', 'accession_number' => 'ACC-3']);
        RecordRights::factory()->for($embargoed)->create(['access_level' => 'public', 'permission_status' => 'approved', 'embargo_until' => now()->addMonth()]);
        $this->record($batch, ['title' => 'Still going', 'accession_number' => 'ACC-4', 'processing_status' => ProcessingStatus::Processing]);

        $this->actingAs($batch->creator)
            ->postJson(route('batches.zip.store', $batch), ['thumbnails' => true])
            ->assertAccepted();

        $batch->refresh();
        $this->assertSame(BatchZipStatus::Ready, $batch->zip_status);
        $entries = $this->entries($batch);
        $folder = 'acc-1-opening-remarks';

        $this->assertEqualsCanonicalizing([
            'manifest.csv',
            "{$folder}/{$folder}.json",
            "{$folder}/{$folder}.md",
            "{$folder}/{$folder}.srt",
            "{$folder}/{$folder}.vtt",
            "{$folder}/{$folder}.txt",
            "{$folder}/{$folder}.csv",
            "{$folder}/thumbnail.jpg",
        ], array_keys($entries));
        $this->assertStringContainsString('Good morning.', $entries["{$folder}/{$folder}.srt"]);
        $manifest = $entries['manifest.csv'];
        $this->assertStringContainsString('ACC-1,"Opening remarks",acc-1-opening-remarks,yes', $manifest);
        $this->assertStringContainsString('security classification is confidential', $manifest);
        $this->assertStringContainsString('under embargo', $manifest);
        $this->assertStringContainsString('still processing', $manifest);

        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $draft->id, 'action' => AuditAction::Exported->value, 'remarks' => "Format: zip (batch {$batch->id})"]);
        $this->assertDatabaseMissing('video_audit_logs', ['record_id' => $classified->id, 'action' => AuditAction::Exported->value]);

        $this->get(route('batches.download', $batch))
            ->assertOk()
            ->assertDownload('town-hall.zip');
    }

    public function test_the_zip_must_be_built_before_it_can_be_downloaded(): void
    {
        $batch = Batch::factory()->create();

        $this->actingAs($batch->creator)->get(route('batches.download', $batch))->assertNotFound();
    }

    public function test_a_build_already_running_is_not_started_twice(): void
    {
        Queue::fake([BuildBatchZipJob::class]);
        $batch = Batch::factory()->create();

        $this->actingAs($batch->creator)->postJson(route('batches.zip.store', $batch))->assertAccepted();
        $this->postJson(route('batches.zip.store', $batch))->assertAccepted();

        Queue::assertPushed(BuildBatchZipJob::class, 1);
        $this->assertSame(BatchZipStatus::Building, $batch->refresh()->zip_status);
    }

    public function test_another_encoder_can_neither_build_nor_download_the_zip(): void
    {
        Queue::fake([BuildBatchZipJob::class]);
        $batch = Batch::factory()->create(['zip_status' => BatchZipStatus::Ready, 'zip_path' => 'batches/x.zip']);
        $this->actingAs(User::factory()->encoder()->create());

        $this->postJson(route('batches.zip.store', $batch))->assertForbidden();
        $this->get(route('batches.download', $batch))->assertForbidden();
        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(Batch $batch, array $attributes): Record
    {
        return Record::factory()->create([
            'batch_id' => $batch->id,
            'created_by' => $batch->created_by,
            'processing_status' => ProcessingStatus::Ready,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function entries(Batch $batch): array
    {
        $local = tempnam(sys_get_temp_dir(), 'zip-test-');
        file_put_contents($local, Storage::disk('media')->get((string) $batch->zip_path));
        $zip = new ZipArchive;
        $zip->open($local);
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $entries[$name] = (string) $zip->getFromName($name);
        }

        $zip->close();
        unlink($local);

        return $entries;
    }
}
