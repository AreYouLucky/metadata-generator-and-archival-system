<?php

namespace Tests\Feature\Internal;

use App\Enums\AuditAction;
use App\Enums\ChecksumStatus;
use App\Enums\FileRole;
use App\Enums\FixityTrigger;
use App\Enums\MetadataSource;
use App\Enums\PipelineStage;
use App\Enums\SubjectType;
use App\Jobs\RunFixityCheckJob;
use App\Models\City;
use App\Models\Province;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\Region;
use App\Models\User;
use App\Notifications\FixityMismatch;
use App\Services\InternalSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phase 3 in the pipeline and its callbacks: the access copy, AI suggestions,
 * added files and fixity checks.
 */
class ArchivePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_proxy_becomes_the_records_access_copy_and_is_streamed_instead_of_an_unplayable_master(): void
    {
        Storage::fake('media');
        $master = RecordFile::factory()->create(['mime_type' => 'application/mxf', 'storage_location' => 'records/m/master.mxf']);
        $record = $master->record;
        $master->technicalMetadata()->create(['video_codec' => 'prores', 'audio_codec' => 'pcm_s24le', 'audio_tracks' => []]);
        $record->stages()->create(['stage' => PipelineStage::Proxy]);
        Storage::disk('media')->put("records/{$record->id}/proxy/access.mp4", 'mp4');

        $this->postSigned("/internal/records/{$record->id}/results/proxy", [
            'path' => "records/{$record->id}/proxy/access.mp4",
            'md5' => str_repeat('c', 32),
            'sha256' => str_repeat('d', 64),
            'file_size' => 3,
            'probe' => [
                'file_format' => 'QuickTime / MOV',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'resolution' => '1920x1080',
                'audio_tracks' => [],
                'container_tags' => [],
            ],
        ])->assertOk();

        $proxy = $record->files()->where('role', FileRole::Proxy)->sole();
        $this->assertSame([str_repeat('d', 64), ChecksumStatus::Ok, 'h264'], [$proxy->sha256_checksum, $proxy->checksum_status, $proxy->technicalMetadata?->video_codec]);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'record_file_id' => $proxy->id, 'action' => AuditAction::Transcoded->value]);

        $this->actingAs($record->creator)
            ->get(route('records.media', $record))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_ai_suggestions_become_unconfirmed_subjects_keywords_and_a_matched_location(): void
    {
        $record = Record::factory()->create();
        $record->subjects()->create(['subject' => 'DOST', 'type' => SubjectType::Organisation, 'source' => MetadataSource::Manual, 'is_confirmed' => true]);
        $region = Region::query()->create(['name' => 'Region VII', 'description' => 'Central Visayas']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Cebu']);
        $city = City::query()->create(['province_id' => $province->id, 'name' => 'Cebu City']);

        $this->postSigned("/internal/records/{$record->id}/results/generate_metadata", [
            'preset' => 'generic',
            'custom_prompt' => null,
            'model' => 'gpt-6.2-sol',
            'metadata' => [
                'titles' => ['Fair opens'],
                'description' => 'D',
                'summary' => 'S',
                'tags' => [],
                'keywords' => ['innovation', 'Innovation'],
                'hashtags' => [],
                'chapters' => [],
                'suggestedSubjects' => [['name' => 'dost', 'type' => 'organisation'], ['name' => 'Jane Doe', 'type' => 'person']],
                'suggestedLocation' => ['text' => 'Cebu City', 'region' => 'Central Visayas', 'province' => 'cebu', 'city' => 'Cebu City', 'barangay' => 'Nowhere'],
            ],
            'usage' => [],
        ])->assertOk();

        $this->assertSame(['DOST' => true, 'Jane Doe' => false], $record->subjects()->orderBy('subject')->pluck('is_confirmed', 'subject')->all());
        $this->assertSame([['innovation', 'ai', false]], $record->keywords()->get()->map(fn ($k): array => [$k->keyword, $k->source->value, $k->is_confirmed])->all());
        $location = $record->activeMetadata()->sole()->payload['suggestedLocation'];
        $this->assertSame([$region->id, $province->id, $city->id, null], [$location['region_id'], $location['prov_id'], $location['city_id'], $location['brgy_id']]);
    }

    public function test_an_added_files_checksums_and_probe_are_stored_with_an_ingest_fixity_check(): void
    {
        $file = RecordFile::factory()->create(['role' => FileRole::Mezzanine, 'md5_checksum' => null, 'sha256_checksum' => null, 'checksum_status' => ChecksumStatus::Pending, 'expected_sha256' => str_repeat('e', 64)]);

        $this->postSigned("/internal/record-files/{$file->id}/results/checksum", ['md5' => str_repeat('a', 32), 'sha256' => str_repeat('b', 64), 'file_size' => 10])->assertOk();
        $this->postSigned("/internal/record-files/{$file->id}/results/probe", ['video_codec' => 'prores', 'audio_tracks' => [], 'container_tags' => [], 'raw_probe' => []])->assertOk();

        $file->refresh();
        $this->assertSame(ChecksumStatus::Mismatch, $file->checksum_status);
        $this->assertSame('prores', $file->technicalMetadata?->video_codec);
        $this->assertDatabaseHas('fixity_checks', ['record_file_id' => $file->id, 'algorithm' => 'sha256', 'matched' => false, 'triggered_by' => FixityTrigger::Ingest->value]);
    }

    public function test_the_master_cannot_be_overwritten_through_the_added_file_callback(): void
    {
        $master = RecordFile::factory()->create();

        $this->postSigned("/internal/record-files/{$master->id}/results/checksum", ['md5' => str_repeat('a', 32), 'sha256' => str_repeat('b', 64), 'file_size' => 1])->assertNotFound();
    }

    public function test_check_now_queues_the_master_and_mezzanine_but_not_the_proxy(): void
    {
        Queue::fake([RunFixityCheckJob::class]);
        $master = RecordFile::factory()->create();
        RecordFile::factory()->for($master->record)->create(['role' => FileRole::Mezzanine]);
        RecordFile::factory()->for($master->record)->create(['role' => FileRole::Proxy]);

        $this->actingAs($master->record->creator)
            ->postJson(route('records.fixity', $master->record))
            ->assertAccepted()
            ->assertJsonPath('queued', 2);

        Queue::assertPushed(RunFixityCheckJob::class, 2);
        Queue::assertPushed(RunFixityCheckJob::class, fn (RunFixityCheckJob $job): bool => $job->trigger === FixityTrigger::User);
    }

    public function test_a_matching_fixity_check_is_stored_and_logged(): void
    {
        Notification::fake();
        $file = RecordFile::factory()->create(['md5_checksum' => str_repeat('a', 32), 'sha256_checksum' => str_repeat('b', 64)]);

        $this->postSigned("/internal/record-files/{$file->id}/fixity", ['triggered_by' => 'user', 'md5' => str_repeat('a', 32), 'sha256' => str_repeat('b', 64)])
            ->assertOk()
            ->assertJsonPath('matched', true);

        $this->assertSame(2, $file->fixityChecks()->where('matched', true)->count());
        $this->assertDatabaseHas('video_audit_logs', ['record_file_id' => $file->id, 'action' => AuditAction::ChecksumVerified->value]);
        Notification::assertNothingSent();
    }

    public function test_a_changed_file_is_flagged_and_its_owner_and_the_admins_are_told(): void
    {
        Notification::fake();
        $file = RecordFile::factory()->create(['sha256_checksum' => str_repeat('b', 64)]);
        $admin = User::factory()->admin()->create();
        $otherEncoder = User::factory()->encoder()->create();

        $this->postSigned("/internal/record-files/{$file->id}/fixity", ['triggered_by' => 'schedule', 'md5' => str_repeat('0', 32), 'sha256' => str_repeat('f', 64)])
            ->assertJsonPath('matched', false);

        $this->assertSame(ChecksumStatus::Mismatch, $file->refresh()->checksum_status);
        $this->assertDatabaseHas('video_audit_logs', ['record_file_id' => $file->id, 'action' => AuditAction::ChecksumFailed->value]);
        Notification::assertSentTo([$file->record->creator, $admin], FixityMismatch::class);
        Notification::assertNotSentTo($otherEncoder, FixityMismatch::class);
    }

    public function test_the_scheduled_check_queues_only_files_not_checked_within_the_interval(): void
    {
        Queue::fake([RunFixityCheckJob::class]);
        config(['archive.fixity_check_interval_days' => 90]);
        $stale = RecordFile::factory()->create(['created_at' => now()->subDays(200)]);
        $recent = RecordFile::factory()->create(['created_at' => now()->subDays(200)]);
        $recent->fixityChecks()->create(['algorithm' => 'sha256', 'expected' => 'x', 'actual' => 'x', 'matched' => true, 'triggered_by' => FixityTrigger::Schedule, 'checked_at' => now()->subDays(10)]);
        RecordFile::factory()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('archive:fixity')->assertSuccessful();

        Queue::assertPushed(RunFixityCheckJob::class, 1);
        Queue::assertPushed(RunFixityCheckJob::class, fn (RunFixityCheckJob $job): bool => $job->file->is($stale) && $job->trigger === FixityTrigger::Schedule);
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
