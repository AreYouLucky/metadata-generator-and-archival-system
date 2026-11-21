<?php

namespace App\Jobs;

use App\Events\RecordDescriptiveSuggested;
use App\Models\Genre;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\VideoType;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\PipelineRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Fill with AI" for Archive → Descriptive: asks the AI service for catalogue
 * fields written from the active generation's summary, chapters and keywords
 * plus the project and series (a few hundred tokens, never the transcript).
 * Genre and video type come back as names from the archive's lists and are
 * matched to ids here. Nothing is saved: the workspace fills the form.
 */
class SuggestDescriptiveJob implements ShouldQueue
{
    use Queueable;

    public const int LOCK_SECONDS = 180;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public Record $record) {}

    public static function lockKey(Record $record): string
    {
        return "records:{$record->id}:suggesting-descriptive";
    }

    public function handle(AiServiceClient $client, PipelineRecorder $recorder): void
    {
        try {
            $payload = $this->record->activeMetadata()->first(['id', 'record_id', 'payload'])->payload ?? [];
            $genres = Genre::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id');
            $types = VideoType::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id');

            try {
                $result = $client->suggestDescriptive([
                    'record_id' => $this->record->id,
                    'summary' => mb_substr((string) ($payload['summary'] ?? ''), 0, 5000),
                    'chapters' => array_slice(array_map(
                        fn (array $chapter): string => gmdate('G:i:s', (int) ($chapter['start'] ?? 0)).' '.($chapter['title'] ?? ''),
                        array_filter(is_array($payload['chapters'] ?? null) ? $payload['chapters'] : [], 'is_array'),
                    ), 0, 200),
                    'keywords' => array_slice(array_values(array_filter(is_array($payload['keywords'] ?? null) ? $payload['keywords'] : [], 'is_string')), 0, 50),
                    'current_title' => mb_substr($this->record->title, 0, 250),
                    'language' => $this->record->language,
                    'project' => self::event($this->record->project()->first(['id', 'title', 'date', 'end_date', 'location'])),
                    'series' => self::event($this->record->projectSeries()->first(['id', 'title', 'date', 'end_date', 'location'])),
                    'genres' => $genres->values()->all(),
                    'video_types' => $types->values()->all(),
                ]);
            } catch (RequestException $exception) {
                $detail = $exception->response->json('detail');
                $this->broadcast([], is_string($detail) ? $detail : 'The AI service could not write suggestions.');

                return;
            } catch (ConnectionException) {
                $this->broadcast([], 'The AI service could not be reached. Try again in a moment.');

                return;
            }

            /** @var array<string, mixed> $fields */
            $fields = $result['fields'] ?? [];
            $recorder->recordUsage($this->record, $result['usage'] ?? []);

            $this->broadcast([
                'title' => is_string($fields['title'] ?? null) ? $fields['title'] : null,
                'alternative_title' => $fields['alternative_title'] ?? null,
                'description' => $fields['description'] ?? null,
                'genre_id' => $genres->search($fields['genre'] ?? null) ?: null,
                'video_type_id' => $types->search($fields['video_type'] ?? null) ?: null,
                'country_origin' => $fields['country_origin'] ?? null,
                'date_recorded' => $fields['date_recorded'] ?? null,
                'date_recorded_precision' => $fields['date_recorded_precision'] ?? null,
            ]);
        } finally {
            Cache::forget(self::lockKey($this->record));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::lockKey($this->record));
        $this->broadcast([], 'Suggestions could not be written. Try again.');
        Log::warning('Descriptive suggestions failed', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }

    /**
     * @return array{title: string, date: string|null, end_date: string|null, location: string|null}|null
     */
    private static function event(Project|ProjectSeries|null $event): ?array
    {
        return $event === null ? null : [
            'title' => mb_substr($event->title, 0, 250),
            'date' => $event->date?->toDateString(),
            'end_date' => $event->end_date?->toDateString(),
            'location' => $event->location === null ? null : mb_substr($event->location, 0, 250),
        ];
    }

    /**
     * Broadcast with rescue(): a Reverb outage must not fail the job.
     *
     * @param  array<string, mixed>  $fields
     */
    private function broadcast(array $fields, ?string $error = null): void
    {
        rescue(fn () => RecordDescriptiveSuggested::dispatch($this->record, $fields, $error), report: false);
    }
}
