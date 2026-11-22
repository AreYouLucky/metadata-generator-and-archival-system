<?php

namespace App\Jobs;

use App\Enums\FileRole;
use App\Models\Record;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\AudioExports;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the AI service to export the master's audio as MP3 or WAV
 * (POST /jobs/export-audio); the result arrives on the signed
 * /internal/records/{record}/audio-export callback.
 */
class StartAudioExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Record $record,
        public string $format,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AiServiceClient $client): void
    {
        $master = $this->record->files()->where('role', FileRole::Master)->firstOrFail();
        $duration = $master->technicalMetadata()->value('duration');

        try {
            $client->exportAudio([
                'record_id' => $this->record->id,
                'video_path' => $master->storage_location,
                'format' => $this->format,
                'output_path' => AudioExports::path($this->record, $this->format),
                'duration' => $duration !== null ? (float) $duration : null,
            ]);
        } catch (RequestException $exception) {
            // A refused request won't succeed on retry; outages and throttling are waited out.
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AudioExports::class)->finished($this->record, $this->format, 'The AI service could not start the audio export. Try again later.');

        Log::warning('Audio export could not start', ['record_id' => $this->record->id, 'format' => $this->format, 'error' => $exception?->getMessage()]);
    }
}
