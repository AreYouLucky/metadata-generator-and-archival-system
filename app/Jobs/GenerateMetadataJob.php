<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineOptions;
use App\Services\Records\PipelineRecorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Regenerates AI metadata from the current (possibly edited) transcript. The
 * AI service reuses its cached summary of the transcript when the text hasn't
 * changed, so a regeneration usually costs only the preset-specific copy.
 */
class GenerateMetadataJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 330;

    public int $uniqueFor = 600;

    public function __construct(
        public Record $record,
        public PipelineOptions $options,
    ) {}

    /**
     * One regeneration per record at a time.
     */
    public function uniqueId(): string
    {
        return $this->record->id;
    }

    public function handle(AiServiceClient $client, PipelineRecorder $recorder): void
    {
        $transcript = $this->record->originalTranscript()->first();

        if ($transcript === null) {
            $recorder->progress($this->record, PipelineStage::GenerateMetadata, StageStatus::Failed, message: 'There is no transcript to generate metadata from.');

            return;
        }

        $segments = $transcript->segments()->get(['start', 'end', 'text'])
            ->map(fn (RecordSegment $segment): array => [
                'start' => $segment->start,
                'end' => $segment->end,
                'text' => $segment->text,
            ])
            ->filter(fn (array $segment): bool => trim($segment['text']) !== '')
            ->values()
            ->all();

        if ($segments === []) {
            $recorder->progress($this->record, PipelineStage::GenerateMetadata, StageStatus::Failed, message: 'The transcript is empty.');

            return;
        }

        $duration = $this->record->masterFile()->first()?->technicalMetadata()->value('duration');

        try {
            $result = $client->generateMetadata([
                'record_id' => $this->record->id,
                'transcript' => $segments,
                'language' => $this->record->language,
                'preset' => $this->options->presetRules(),
                'custom_prompt' => $this->options->customPrompt,
                'title_count' => PipelineOptions::TITLE_COUNT,
                'duration' => $duration !== null ? (float) $duration : null,
                'fresh' => true,
            ]);
        } catch (RequestException $exception) {
            // The AI service explains provider failures in `detail`; don't retry those.
            $detail = $exception->response->json('detail');
            $recorder->progress($this->record, PipelineStage::GenerateMetadata, StageStatus::Failed, message: is_string($detail) ? $detail : 'Metadata generation failed.');
            $this->fail($exception);

            return;
        }

        /** @var array{model: string, metadata: array<string, mixed>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>} $result */
        $recorder->storeMetadata($this->record, [
            ...$result,
            'preset' => $this->options->preset->value,
            'custom_prompt' => $this->options->customPrompt,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $stage = $this->record->stages()->where('stage', PipelineStage::GenerateMetadata)->first();

        if ($stage?->status === StageStatus::Running) {
            app(PipelineRecorder::class)->progress($this->record, PipelineStage::GenerateMetadata, StageStatus::Failed, message: 'Metadata generation failed. Try again.');
        }

        Log::warning('Metadata generation failed', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }
}
