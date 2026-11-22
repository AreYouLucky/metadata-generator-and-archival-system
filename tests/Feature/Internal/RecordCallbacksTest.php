<?php

namespace Tests\Feature\Internal;

use App\Enums\AuditAction;
use App\Enums\ChecksumStatus;
use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Events\RecordProgressUpdated;
use App\Events\RecordStageCompleted;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordStage;
use App\Services\InternalSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RecordCallbacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_running_stage_moves_the_record_to_processing_and_is_broadcast(): void
    {
        $record = $this->recordWithStages();
        Event::fake([RecordProgressUpdated::class]);

        $this->postSigned("/internal/records/{$record->id}/progress", [
            'stage' => 'transcribe',
            'status' => 'running',
            'progress' => 40,
            'message' => null,
        ])->assertOk();

        $stage = $this->stage($record, PipelineStage::Transcribe);
        $this->assertSame(StageStatus::Running, $stage->status);
        $this->assertSame(40, $stage->progress);
        $this->assertNotNull($stage->started_at);
        $this->assertSame(ProcessingStatus::Processing, $record->refresh()->processing_status);
        Event::assertDispatched(RecordProgressUpdated::class, fn (RecordProgressUpdated $event): bool => $event->stage->is($stage));
    }

    public function test_checksums_are_stored_verified_and_logged(): void
    {
        $record = $this->recordWithStages();
        $file = $record->files()->sole();
        Event::fake([RecordStageCompleted::class, RecordProgressUpdated::class]);

        $this->postSigned("/internal/records/{$record->id}/results/checksum", [
            'md5' => str_repeat('a', 32),
            'sha256' => str_repeat('b', 64),
            'file_size' => 1234,
        ])->assertOk();

        $file->refresh();
        $this->assertSame(str_repeat('b', 64), $file->sha256_checksum);
        $this->assertSame(1234, $file->file_size);
        $this->assertSame(ChecksumStatus::Ok, $file->checksum_status);
        $this->assertSame(StageStatus::Done, $this->stage($record, PipelineStage::Checksum)->status);
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'record_file_id' => $file->id,
            'action' => AuditAction::ChecksumVerified->value,
        ]);
        Event::assertDispatched(RecordStageCompleted::class, fn (RecordStageCompleted $event): bool => $event->stage === PipelineStage::Checksum);
    }

    public function test_a_checksum_that_differs_from_the_expected_one_flags_the_file(): void
    {
        $record = $this->recordWithStages(['expected_sha256' => str_repeat('c', 64)]);
        $file = $record->files()->sole();

        $this->postSigned("/internal/records/{$record->id}/results/checksum", [
            'md5' => str_repeat('a', 32),
            'sha256' => str_repeat('b', 64),
            'file_size' => 1234,
        ])->assertOk();

        $this->assertSame(ChecksumStatus::Mismatch, $file->refresh()->checksum_status);
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::ChecksumFailed->value,
            'remarks' => 'SHA-256 did not match the expected checksum supplied at upload.',
        ]);
    }

    public function test_probe_results_become_the_files_technical_metadata(): void
    {
        $record = $this->recordWithStages();

        $this->postSigned("/internal/records/{$record->id}/results/probe", [
            'file_format' => 'QuickTime / MOV',
            'duration' => 312.5,
            'bitrate' => 4500000,
            'start_timecode' => '01:00:00:00',
            'video_codec' => 'prores',
            'video_profile' => '422 HQ',
            'resolution' => '1920x1080',
            'frame_rate' => '25/1',
            'aspect_ratio' => '16:9',
            'scan_type' => 'interlaced_tff',
            'bit_depth' => 10,
            'chroma_subsampling' => '4:2:2',
            'color_space' => 'bt709',
            'color_primaries' => 'bt709',
            'color_transfer' => 'bt709',
            'audio_codec' => 'pcm_s24le',
            'audio_channels' => 2,
            'audio_sample_rate' => 48000,
            'audio_tracks' => [['index' => 1, 'codec' => 'pcm_s24le', 'channels' => 2, 'sampleRate' => 48000, 'language' => 'eng']],
            'container_tags' => ['creation_time' => '2026-07-10T08:00:00Z'],
            'raw_probe' => ['ffprobe' => ['format' => ['format_name' => 'mov']], 'mediainfo' => []],
        ])->assertOk();

        $technical = $record->files()->sole()->technicalMetadata;
        $this->assertSame('prores', $technical->video_codec);
        $this->assertSame(10, $technical->bit_depth);
        $this->assertSame('2026-07-10T08:00:00Z', $technical->raw_probe['container_tags']['creation_time']);
        $this->assertFalse($technical->isBrowserPlayable('video/quicktime'));
    }

    public function test_a_transcript_is_stored_with_its_language_and_usage(): void
    {
        $record = $this->recordWithStages();

        $this->postSigned("/internal/records/{$record->id}/results/transcribe", [
            'provider' => 'local',
            'model' => 'faster-whisper/large-v3',
            'language' => 'tl',
            'language_confidence' => 0.93,
            'duration' => 300,
            'segments' => [
                ['start' => 0, 'end' => 4.2, 'text' => 'Magandang umaga po.'],
                ['start' => 4.2, 'end' => 9, 'text' => 'Welcome to the fair.'],
            ],
            'usage' => [['kind' => 'transcription_minutes', 'quantity' => 5, 'estimated_cost' => 0]],
        ])->assertOk();

        $record->refresh();
        $transcript = $record->originalTranscript;
        $this->assertSame('tl', $record->language);
        $this->assertSame(0.93, $record->language_confidence);
        $this->assertSame('tl', $transcript->language);
        $this->assertSame(['Magandang umaga po.', 'Welcome to the fair.'], $transcript->segments->pluck('original_text')->all());
        $this->assertDatabaseHas('usage_records', [
            'record_id' => $record->id,
            'user_id' => $record->created_by,
            'kind' => 'transcription_minutes',
        ]);
    }

    public function test_generated_metadata_becomes_the_active_generation_and_the_record_is_ready(): void
    {
        $record = $this->recordWithStages();
        $record->stages()->where('stage', '!=', PipelineStage::GenerateMetadata)->update(['status' => StageStatus::Done]);
        $previous = $record->metadata()->create(['preset' => 'generic', 'model' => 'old', 'payload' => [], 'is_active' => true]);

        $this->postSigned("/internal/records/{$record->id}/results/generate_metadata", [
            'preset' => 'youtube',
            'custom_prompt' => null,
            'model' => 'gpt-6.2-sol',
            'metadata' => [
                'titles' => ['Regional S&T Fair opens', 'Opening of the S&T Fair'],
                'description' => 'The opening ceremony.',
                'summary' => 'Officials open the fair.',
                'tags' => ['science fair'],
                'keywords' => ['DOST'],
                'hashtags' => ['#STFair'],
                'chapters' => [['start' => 0, 'title' => 'Opening']],
            ],
            'usage' => [
                ['kind' => 'llm_input_tokens', 'quantity' => 1200, 'estimated_cost' => 0.0024],
                ['kind' => 'llm_output_tokens', 'quantity' => 300, 'estimated_cost' => 0.003],
            ],
        ])->assertOk();

        $active = $record->activeMetadata()->sole();
        $this->assertSame('gpt-6.2-sol', $active->model);
        $this->assertSame('Regional S&T Fair opens', $active->payload['selectedTitle']);
        $this->assertFalse($previous->refresh()->is_active);
        $this->assertSame(ProcessingStatus::Ready, $record->refresh()->processing_status);
        $this->assertSame(2, $record->usageRecords()->count());
    }

    public function test_quotes_tone_and_content_flags_are_kept_with_the_generation(): void
    {
        $record = $this->recordWithStages();
        $quote = ['start' => 36.5, 'end' => 41.5, 'speaker' => null, 'text' => 'Buhay ang agham.'];

        $this->postSigned("/internal/records/{$record->id}/results/generate_metadata", [
            'preset' => 'tiktok',
            'custom_prompt' => null,
            'model' => 'gpt-6.2-sol',
            'metadata' => [
                'titles' => ['Fair opens'],
                'description' => 'A caption.',
                'summary' => 'Officials open the fair.',
                'tags' => [],
                'keywords' => [],
                'hashtags' => ['#STFair', '#DOST', '#Science'],
                'chapters' => [],
                'quotes' => [$quote],
                'tone' => 'celebratory',
                'contentFlags' => ['profanity' => false, 'sensitiveTopics' => ['politics']],
            ],
            'usage' => [],
        ])->assertOk();

        $payload = $record->activeMetadata()->sole()->payload;
        $this->assertSame([$quote], $payload['quotes']);
        $this->assertSame('celebratory', $payload['tone']);
        $this->assertSame(['profanity' => false, 'sensitiveTopics' => ['politics']], $payload['contentFlags']);
    }

    public function test_a_failed_stage_marks_the_record_failed_once_every_stage_has_finished(): void
    {
        $record = $this->recordWithStages();
        $record->stages()->whereNot('stage', PipelineStage::Transcribe)->update(['status' => StageStatus::Done]);

        $this->postSigned("/internal/records/{$record->id}/progress", [
            'stage' => 'transcribe',
            'status' => 'failed',
            'progress' => 0,
            'message' => 'CUDA out of memory',
        ])->assertOk();

        $record->refresh();
        $this->assertSame(ProcessingStatus::Failed, $record->processing_status);
        $this->assertSame('Transcription: CUDA out of memory', $record->processing_error);
    }

    public function test_a_stage_cannot_be_reported_done_through_the_progress_route(): void
    {
        $record = $this->recordWithStages();

        $this->postSigned("/internal/records/{$record->id}/progress", ['stage' => 'transcribe', 'status' => 'done', 'progress' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_malformed_results_are_rejected_even_when_signed(): void
    {
        $record = $this->recordWithStages();

        $this->postSigned("/internal/records/{$record->id}/results/checksum", ['md5' => 'nope', 'sha256' => 'nope', 'file_size' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['md5', 'sha256', 'file_size']);

        $this->assertSame(ChecksumStatus::Pending, $record->files()->sole()->checksum_status);
    }

    public function test_unsigned_results_and_unknown_stages_are_refused(): void
    {
        $record = $this->recordWithStages();

        $this->postJson("/internal/records/{$record->id}/results/checksum", ['md5' => str_repeat('a', 32)])->assertUnauthorized();
        $this->postSigned("/internal/records/{$record->id}/results/diarize", [])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $fileAttributes
     */
    private function recordWithStages(array $fileAttributes = []): Record
    {
        $record = Record::factory()->create();
        RecordFile::factory()->for($record)->create([
            'md5_checksum' => null,
            'sha256_checksum' => null,
            'checksum_status' => ChecksumStatus::Pending,
            ...$fileAttributes,
        ]);
        $record->stages()->createMany(array_map(
            fn (PipelineStage $stage): array => ['stage' => $stage],
            PipelineStage::active(),
        ));

        return $record;
    }

    private function stage(Record $record, PipelineStage $stage): RecordStage
    {
        return $record->stages()->where('stage', $stage)->sole();
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
