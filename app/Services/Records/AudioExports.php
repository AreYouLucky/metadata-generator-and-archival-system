<?php

namespace App\Services\Records;

use App\Events\RecordAudioExportUpdated;
use App\Jobs\StartAudioExportJob;
use App\Models\Record;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The record's audio as a file to download (MP3 or WAV), made from the
 * master by the AI service in the background and kept at
 * `records/{id}/exports/audio.{format}` on the private media disk. A finished
 * file is reused; while one is being made, or after it failed, its state is
 * kept in the cache. Not an archival file, so it isn't in `record_files`.
 */
class AudioExports
{
    /**
     * Format => what the Export panel says about it.
     *
     * @var array<string, string>
     */
    public const array FORMATS = [
        'mp3' => 'MP3 · 192 kbps stereo',
        'wav' => 'WAV · uncompressed, 16-bit',
    ];

    public const array MIME_TYPES = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
    ];

    /**
     * How long "being made" holds before another request may start again
     * (a worker that died never reports).
     */
    private const int PROCESSING_SECONDS = 3600;

    private const int FAILED_SECONDS = 86400;

    public static function path(Record $record, string $format): string
    {
        return "records/{$record->id}/exports/audio.{$format}";
    }

    /**
     * Start making the file unless it exists or is already being made.
     * Returns its state afterwards.
     *
     * @return 'ready'|'processing'
     */
    public function start(Record $record, string $format): string
    {
        if ($this->disk()->exists(self::path($record, $format))) {
            return 'ready';
        }

        $key = self::key($record, $format);
        $state = Cache::get($key);

        if (is_array($state) && ($state['status'] ?? null) === 'processing') {
            return 'processing';
        }

        // A failed attempt may be retried; add() makes two requests at once start one export.
        if (is_array($state) && ($state['status'] ?? null) === 'failed') {
            Cache::forget($key);
        }

        if (Cache::add($key, ['status' => 'processing'], self::PROCESSING_SECONDS)) {
            StartAudioExportJob::dispatch($record, $format);
        }

        return 'processing';
    }

    /**
     * The AI service reported (or could not be reached): forget "being made",
     * remember a failure, and tell the workspace.
     */
    public function finished(Record $record, string $format, ?string $error): void
    {
        $key = self::key($record, $format);

        if ($error === null) {
            Cache::forget($key);
        } else {
            Cache::put($key, ['status' => 'failed', 'error' => mb_substr($error, 0, 500)], self::FAILED_SECONDS);
        }

        rescue(fn () => RecordAudioExportUpdated::dispatch($record, $format, $error === null ? 'ready' : 'failed', $error), report: false);
    }

    /**
     * Every format's state for the Export panel.
     *
     * @return list<array{format: string, label: string, status: 'none'|'processing'|'ready'|'failed', file_size: int|null, error: string|null, download_url: string|null}>
     */
    public function present(Record $record): array
    {
        $disk = $this->disk();
        $result = [];

        foreach (self::FORMATS as $format => $label) {
            $path = self::path($record, $format);
            $state = Cache::get(self::key($record, $format));
            $ready = $disk->exists($path);
            $status = match (true) {
                $ready => 'ready',
                is_array($state) && ($state['status'] ?? null) === 'processing' => 'processing',
                is_array($state) && ($state['status'] ?? null) === 'failed' => 'failed',
                default => 'none',
            };

            $result[] = [
                'format' => $format,
                'label' => $label,
                'status' => $status,
                'file_size' => $ready ? $disk->size($path) : null,
                'error' => ! $ready && is_array($state) ? ($state['error'] ?? null) : null,
                'download_url' => $ready ? route('records.audio-export.show', [$record, $format]) : null,
            ];
        }

        return $result;
    }

    private static function key(Record $record, string $format): string
    {
        return "audio-export:{$record->id}:{$format}";
    }

    private function disk(): Filesystem
    {
        return Storage::disk('media');
    }
}
