<?php

namespace App\Jobs;

use App\Http\Requests\Encoder\StoreTranslationRequest;
use App\Models\Record;
use App\Models\RecordTranscript;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\TranslationRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands a translation to the AI service (POST /jobs/translate) with the
 * original's current (possibly edited) lines: ids and text only. The lines
 * come back on the signed translation callbacks.
 */
class StartTranslationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Record $record,
        public RecordTranscript $translation,
    ) {}

    /**
     * Back off while the AI service restarts.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(AiServiceClient $client, TranslationRecorder $translations): void
    {
        $original = $this->record->originalTranscript()->first(['id', 'record_id', 'language']);
        $lines = [];

        foreach ($original?->segments()->toBase()->get(['id', 'text']) ?? [] as $row) {
            if (trim((string) $row->text) !== '') {
                $lines[] = ['id' => (int) $row->id, 'text' => (string) $row->text];
            }
        }

        if ($original === null || $lines === []) {
            $translations->fail($this->record, $this->translation, 'The transcript is empty, so there is nothing to translate.');

            return;
        }

        try {
            $client->translate([
                'record_id' => $this->record->id,
                'transcript_id' => $this->translation->id,
                'source_language' => $original->language === 'und' ? null : $original->language,
                'target_language' => $this->translation->language,
                'target_language_name' => StoreTranslationRequest::languages()[$this->translation->language] ?? $this->translation->language,
                'lines' => $lines,
            ]);
        } catch (RequestException $exception) {
            // A rejected request won't succeed on retry; outages and throttling are worth waiting out.
            if ($exception->response->clientError() && $exception->response->status() !== 429) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(TranslationRecorder::class)->fail($this->record, $this->translation, 'The AI service could not start the translation. Try again.');
        Log::warning('Translation could not start', ['record_id' => $this->record->id, 'error' => $exception?->getMessage()]);
    }
}
