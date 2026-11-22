<?php

namespace Tests\Feature\Api;

use App\Enums\AttachmentKind;
use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Enums\MetadataSource;
use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use App\Jobs\StartImportJob;
use App\Jobs\StartProcessingJob;
use App\Models\Project;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Public API (/api/v1): Sanctum tokens acting with their owner's
 * permissions and abilities; exports only of records that may leave the
 * archive.
 */
class RecordApiTest extends TestCase
{
    use RefreshDatabase;

    private const string MP4_BYTES = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    private const string WEBHOOK = 'https://93.184.215.14/hooks/archive';

    public function test_a_request_without_a_token_is_refused(): void
    {
        $this->getJson(route('api.v1.records.index'))->assertUnauthorized();
    }

    public function test_a_token_lists_only_its_owners_records(): void
    {
        $encoder = User::factory()->encoder()->create();
        $own = Record::factory()->for($encoder, 'creator')->create();
        Record::factory()->create();
        Sanctum::actingAs($encoder, ['records:read']);

        $this->getJson(route('api.v1.records.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.processing_status', 'queued')
            ->assertJsonPath('data.0.links.self', route('api.v1.records.show', $own))
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_token_without_the_read_ability_cannot_list_records(): void
    {
        Sanctum::actingAs(User::factory()->encoder()->create(), ['records:create']);

        $this->getJson(route('api.v1.records.index'))->assertForbidden();
    }

    public function test_a_viewer_account_cannot_use_the_api(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['records:read']);

        $this->getJson(route('api.v1.records.index'))->assertForbidden();
    }

    public function test_a_deactivated_encoders_token_stops_working(): void
    {
        Sanctum::actingAs(User::factory()->encoder()->inactive()->create(), ['records:read']);

        $this->getJson(route('api.v1.records.index'))->assertForbidden();
    }

    public function test_a_record_shows_its_stages_and_why_it_cannot_be_exported_yet(): void
    {
        $record = Record::factory()->create();
        $record->stages()->create(['stage' => PipelineStage::Transcribe, 'status' => StageStatus::Running, 'progress' => 40]);
        Sanctum::actingAs($record->creator, ['records:read']);

        $this->getJson(route('api.v1.records.show', $record))
            ->assertOk()
            ->assertJsonPath('data.review_status', 'draft')
            ->assertJsonPath('data.stages.0.stage', 'transcribe')
            ->assertJsonPath('data.stages.0.progress', 40)
            ->assertJsonPath('data.exportable', false)
            ->assertJsonPath('data.export_blockers', ['It is not approved.'])
            ->assertJsonPath('data.links.export', null);
    }

    public function test_another_encoders_record_cannot_be_read(): void
    {
        $record = Record::factory()->create();
        Sanctum::actingAs(User::factory()->encoder()->create(), ['records:read']);

        $this->getJson(route('api.v1.records.show', $record))->assertForbidden();
    }

    public function test_a_record_is_created_from_a_web_address_with_a_webhook(): void
    {
        Queue::fake([StartImportJob::class]);
        $encoder = $this->encoderWithSecret();
        $project = Project::factory()->create();
        Sanctum::actingAs($encoder, ['records:create']);

        $response = $this->postJson(route('api.v1.records.store'), [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'project_id' => $project->id,
            'webhook_url' => self::WEBHOOK,
        ])->assertCreated();

        $record = Record::findOrFail($response->json('data.id'));
        $this->assertSame($encoder->id, $record->created_by);
        $this->assertSame(self::WEBHOOK, $record->webhook_url);
        $response->assertJsonPath('data.webhook.url', self::WEBHOOK);
        Queue::assertPushed(StartImportJob::class, fn (StartImportJob $job): bool => $job->record->is($record));
    }

    public function test_a_record_is_created_from_a_sent_file(): void
    {
        Storage::fake('media');
        Queue::fake([StartProcessingJob::class]);
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create();
        Sanctum::actingAs($encoder, ['records:create']);

        $response = $this->post(route('api.v1.records.store'), [
            'file' => UploadedFile::fake()->createWithContent('Town hall.mp4', self::MP4_BYTES),
            'project_id' => $project->id,
        ], ['Accept' => 'application/json'])->assertCreated();

        $record = Record::findOrFail($response->json('data.id'));
        $master = $record->files()->where('role', FileRole::Master)->sole();
        $this->assertSame('Town hall', $record->title);
        $this->assertSame('video/mp4', $master->mime_type);
        Storage::disk('media')->assertExists($master->storage_location);
        Queue::assertPushed(StartProcessingJob::class, fn (StartProcessingJob $job): bool => $job->record->is($record));
    }

    public function test_a_token_without_the_create_ability_cannot_create_records(): void
    {
        Sanctum::actingAs(User::factory()->encoder()->create(), ['records:read']);

        $this->postJson(route('api.v1.records.store'), ['url' => 'https://www.youtube.com/watch?v=x'])->assertForbidden();
        $this->assertSame(0, Record::query()->count());
    }

    public function test_a_webhook_needs_a_signing_secret(): void
    {
        Sanctum::actingAs(User::factory()->encoder()->create(), ['records:create']);

        $this->postJson(route('api.v1.records.store'), [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'project_id' => Project::factory()->create()->id,
            'webhook_url' => self::WEBHOOK,
        ])->assertJsonValidationErrors(['webhook_url' => 'Create a webhook signing secret in Settings → API tokens first.']);
    }

    public function test_a_webhook_must_be_a_public_https_address(): void
    {
        Sanctum::actingAs($this->encoderWithSecret(), ['records:create']);
        $project = Project::factory()->create();
        $payload = ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'project_id' => $project->id];

        $this->postJson(route('api.v1.records.store'), [...$payload, 'webhook_url' => 'http://example.org/hook'])
            ->assertJsonValidationErrors(['webhook_url' => 'The webhook address must use https.']);
        $this->postJson(route('api.v1.records.store'), [...$payload, 'webhook_url' => 'https://10.0.0.5/hook'])
            ->assertJsonValidationErrors(['webhook_url' => 'The webhook address points to a local or private network.']);
    }

    public function test_an_export_is_refused_until_the_record_may_leave_the_archive(): void
    {
        $record = Record::factory()->submitted()->create();
        Sanctum::actingAs($record->creator, ['records:read']);

        $this->getJson(route('api.v1.records.export', $record))->assertForbidden();
    }

    public function test_an_export_leaves_out_release_forms_and_unconfirmed_keywords_and_is_logged(): void
    {
        $record = Record::factory()->approved()->create();
        $record->keywords()->create(['keyword' => 'confirmed-word', 'source' => MetadataSource::Manual, 'is_confirmed' => true]);
        $record->keywords()->create(['keyword' => 'ai-guess', 'source' => MetadataSource::Ai, 'is_confirmed' => false]);
        $this->attach($record, AttachmentKind::ReleaseForm, 'release-juan-dela-cruz.pdf');
        $this->attach($record, AttachmentKind::Sidecar, 'captions.scc');
        Sanctum::actingAs($record->creator, ['records:read']);

        $response = $this->get(route('api.v1.records.export', [$record, 'format' => 'json']))->assertOk();

        $document = json_decode((string) $response->getContent(), true);
        $this->assertSame(['confirmed-word'], array_column($document['descriptive']['keywords'], 'keyword'));
        $this->assertSame(['captions.scc'], array_column($document['structure']['attachments'], 'name'));
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Exported->value,
            'performed_by' => $record->created_by,
            'remarks' => 'Format: json, via API',
        ]);
    }

    public function test_requests_are_limited_per_token(): void
    {
        config(['archive.api.requests_per_minute' => 1]);
        $encoder = User::factory()->encoder()->create();
        $token = $encoder->createToken('ci', ['records:read'])->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.records.index'))->assertOk();
        $this->withToken($token)->getJson(route('api.v1.records.index'))->assertTooManyRequests();
    }

    private function encoderWithSecret(): User
    {
        $encoder = User::factory()->encoder()->create();
        $encoder->webhook_secret = 'whsec_test';
        $encoder->save();

        return $encoder;
    }

    private function attach(Record $record, AttachmentKind $kind, string $name): void
    {
        $attachment = $record->attachments()->make(['kind' => $kind, 'original_filename' => $name]);
        $attachment->path = "records/{$record->id}/attachments/{$name}";
        $attachment->mime_type = 'application/octet-stream';
        $attachment->file_size = 10;
        $attachment->sha256_checksum = str_repeat('a', 64);
        $attachment->save();
    }
}
