<?php

namespace App\Jobs;

use App\Events\RecordTitlesGenerated;
use App\Http\Requests\Internal\ReceiveStageResultRequest;
use App\Models\Record;
use App\Models\RecordMetadata;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineOptions;
use App\Services\Records\PipelineRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Generate more": asks the AI service for new title options from the
 * generation's summary (a few hundred tokens, never the transcript), appends
 * them to that generation and tells the workspace over Reverb.
 */
class GenerateTitlesJob implements ShouldQueue
{
    use Queueable;

    public const int COUNT = 3;

    public const int LOCK_SECONDS = 180;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(
        public Record $record,
        public int $metadataId,
    ) {}

    public static function lockKey(Record $record): string
    {
        return "records:{$record->id}:generating-titles";
    }

    public function handle(AiServiceClient $client, PipelineRecorder $recorder): void
    {
        try {
            $metadata = $this->record->metadata()->find($this->metadataId);

            if ($metadata === null) {
                $this->report('That generation no longer exists.');

                return;
            }

            $payload = $metadata->payload;
            /** @var list<string> $existing */
            $existing = array_values(array_filter($payload['titles'] ?? [], 'is_string'));
            $room = ReceiveStageResultRequest::MAX_TITLES - count($existing);

            try {
                $result = $client->generateTitles([
                    'record_id' => $this->record->id,
                    'transcript_summary' => mb_substr((string) ($payload['summary'] ?? ''), 0, 5000),
                    'preset' => (new PipelineOptions($metadata->preset))->presetRules(),
                    'count' => max(1, min(self::COUNT, $room)),
                    'avoid' => $existing,
                    'language' => $this->record->language,
                    'custom_prompt' => $metadata->custom_prompt,
                ]);
            } catch (RequestException $exception) {
                $detail = $exception->response->json('detail');
                $this->report(is_string($detail) ? $detail : 'The AI service could not write more titles.');

                return;
            } catch (ConnectionException) {
                $this->report('The AI service could not be reached. Try again in a moment.');

                return;
            }

            $added = $this->append($metadata, $result['titles'] ?? [], $room);
            $recorder->recordUsage($this->record, $result['usage'] ?? []);

            $this->broadcast($added, $added === [] ? 'No new titles came back. Try again.' : null);
        } finally {
            Cache::forget(self::lockKey($this->record));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::lockKey($this->record));
        $this->report('More titles could not be written. Try again.');
        Log::warning('Title generation failed', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }

    /**
     * Add the new titles that aren't already there (case-insensitive), under a
     * row lock so a save from the workspace at the same moment isn't lost.
     *
     * @param  list<string>  $titles
     * @return list<string> the titles actually added
     */
    private function append(RecordMetadata $metadata, array $titles, int $room): array
    {
        return DB::transaction(function () use ($metadata, $titles, $room): array {
            /** @var RecordMetadata $fresh */
            $fresh = RecordMetadata::query()->lockForUpdate()->findOrFail($metadata->id);
            $payload = $fresh->payload;
            /** @var list<string> $current */
            $current = $payload['titles'] ?? [];
            $taken = array_map('mb_strtolower', $current);
            $added = [];

            foreach ($titles as $title) {
                $title = trim((string) $title);

                if ($title === '' || in_array(mb_strtolower($title), $taken, true) || count($added) >= $room) {
                    continue;
                }

                $added[] = mb_substr($title, 0, 300);
                $taken[] = mb_strtolower($title);
            }

            if ($added !== []) {
                $payload['titles'] = [...$current, ...$added];
                $fresh->payload = $payload;
                $fresh->save();
            }

            return $added;
        });
    }

    private function report(string $error): void
    {
        $this->broadcast([], $error);
    }

    /**
     * Broadcast with rescue(): a Reverb outage must not fail the job once titles are saved.
     *
     * @param  list<string>  $titles
     */
    private function broadcast(array $titles, ?string $error): void
    {
        rescue(fn () => RecordTitlesGenerated::dispatch($this->record, $this->metadataId, $titles, $error), report: false);
    }
}
