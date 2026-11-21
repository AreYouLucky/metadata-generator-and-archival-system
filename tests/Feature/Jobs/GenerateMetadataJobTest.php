<?php

namespace Tests\Feature\Jobs;

use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\StageStatus;
use App\Jobs\GenerateMetadataJob;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Services\Records\PipelineOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateMetadataJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_edited_transcript_is_sent_and_the_result_becomes_active(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/generate/metadata' => Http::response([
            'model' => 'gpt-6.2-sol',
            'metadata' => [
                'titles' => ['A title'],
                'description' => 'A description.',
                'summary' => 'A summary.',
                'tags' => [],
                'keywords' => [],
                'hashtags' => [],
                'chapters' => [],
            ],
            'usage' => [['kind' => 'llm_input_tokens', 'quantity' => 800, 'estimated_cost' => 0.0016]],
            'cached' => false,
            'source' => 'core',
        ])]);
        $record = $this->recordWithTranscript('Original line', 'Corrected line');

        GenerateMetadataJob::dispatchSync($record, new PipelineOptions(Preset::Youtube, 'Short.'));

        Http::assertSent(fn (Request $request): bool => $request['transcript'][0]['text'] === 'Corrected line'
            && $request['preset']['name'] === 'youtube'
            && $request['custom_prompt'] === 'Short.'
            && $request['fresh'] === true);
        $active = $record->activeMetadata()->sole();
        $this->assertSame(Preset::Youtube, $active->preset);
        $this->assertSame('A title', $active->payload['selectedTitle']);
        $this->assertSame(1, $record->usageRecords()->count());
    }

    public function test_a_provider_error_fails_the_stage_with_the_services_explanation(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/generate/metadata' => Http::response(['detail' => 'OpenAI rejected the API key.'], 502)]);
        $record = $this->recordWithTranscript('Line', 'Line');
        $record->stages()->create(['stage' => PipelineStage::GenerateMetadata, 'status' => StageStatus::Running]);

        GenerateMetadataJob::dispatchSync($record, new PipelineOptions);

        $stage = $record->stages()->where('stage', PipelineStage::GenerateMetadata)->sole();
        $this->assertSame(StageStatus::Failed, $stage->status);
        $this->assertSame('OpenAI rejected the API key.', $stage->message);
        $this->assertDatabaseCount('record_metadata', 0);
    }

    private function recordWithTranscript(string $original, string $edited): Record
    {
        $record = Record::factory()->create();
        $transcript = RecordTranscript::factory()->for($record)->create();
        RecordSegment::factory()->for($transcript, 'transcript')->create([
            'position' => 1,
            'start' => 0,
            'end' => 3,
            'original_text' => $original,
            'text' => $edited,
        ]);

        return $record;
    }
}
