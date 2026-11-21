<?php

namespace Tests\Feature\Encoder;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\StageStatus;
use App\Jobs\GenerateMetadataJob;
use App\Models\Record;
use App\Models\RecordMetadata;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetadataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_regenerating_queues_a_job_and_marks_the_stage_running(): void
    {
        Queue::fake([GenerateMetadataJob::class]);
        $record = Record::factory()->create();
        RecordTranscript::factory()->for($record)->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.metadata.store', $record), ['preset' => 'youtube', 'custom_prompt' => 'Formal.'])
            ->assertAccepted();

        $this->assertSame(StageStatus::Running, $record->stages()->where('stage', PipelineStage::GenerateMetadata)->sole()->status);
        Queue::assertPushed(
            GenerateMetadataJob::class,
            fn (GenerateMetadataJob $job): bool => $job->record->is($record)
                && $job->options->preset === Preset::Youtube
                && $job->options->customPrompt === 'Formal.',
        );
    }

    public function test_a_second_regeneration_while_one_is_running_is_refused_with_409(): void
    {
        Queue::fake([GenerateMetadataJob::class]);
        $record = Record::factory()->create();
        RecordTranscript::factory()->for($record)->create();
        $record->stages()->create(['stage' => PipelineStage::GenerateMetadata, 'status' => StageStatus::Running]);

        $this->actingAs($record->creator)
            ->postJson(route('records.metadata.store', $record), ['preset' => 'generic'])
            ->assertConflict();

        Queue::assertNotPushed(GenerateMetadataJob::class);
    }

    public function test_regenerating_without_a_transcript_is_refused(): void
    {
        Queue::fake([GenerateMetadataJob::class]);
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.metadata.store', $record), ['preset' => 'generic'])
            ->assertUnprocessable();

        Queue::assertNotPushed(GenerateMetadataJob::class);
    }

    public function test_an_encoder_cannot_regenerate_another_encoders_record(): void
    {
        $record = Record::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.metadata.store', $record), ['preset' => 'generic'])
            ->assertForbidden();
    }

    public function test_edits_are_saved_into_the_generation(): void
    {
        $record = Record::factory()->create();
        $metadata = $this->generation($record, true);

        $this->actingAs($record->creator)
            ->patchJson(route('records.metadata.update', [$record, $metadata]), [
                'titles' => ['First', 'Second'],
                'selectedTitle' => 'Second',
                'description' => 'Edited.',
                'summary' => null,
                'tags' => ['one'],
                'keywords' => [],
                'hashtags' => [],
                'chapters' => [['start' => 0, 'title' => 'Intro']],
            ])
            ->assertOk();

        $payload = $metadata->refresh()->payload;
        $this->assertSame('Second', $payload['selectedTitle']);
        $this->assertSame('Edited.', $payload['description']);
    }

    public function test_a_generation_of_another_record_cannot_be_edited_or_activated(): void
    {
        $record = Record::factory()->create();
        $other = $this->generation(Record::factory()->for($record->creator, 'creator')->create(), false);

        $this->actingAs($record->creator)
            ->postJson(route('records.metadata.activate', [$record, $other]))
            ->assertNotFound();
    }

    public function test_an_older_generation_can_be_made_active_again(): void
    {
        $record = Record::factory()->create();
        $older = $this->generation($record, false);
        $current = $this->generation($record, true);

        $this->actingAs($record->creator)
            ->postJson(route('records.metadata.activate', [$record, $older]))
            ->assertOk();

        $this->assertTrue($older->refresh()->is_active);
        $this->assertFalse($current->refresh()->is_active);
    }

    private function generation(Record $record, bool $active): RecordMetadata
    {
        return $record->metadata()->create([
            'preset' => Preset::Generic,
            'model' => 'gpt-6.2-sol',
            'payload' => ['titles' => ['First'], 'selectedTitle' => 'First'],
            'is_active' => $active,
        ]);
    }
}
