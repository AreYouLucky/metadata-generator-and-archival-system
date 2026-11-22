<?php

namespace App\Services\Records;

use App\Enums\FileRole;
use App\Models\Record;
use App\Services\AiService\AiServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One frame of the master as a JPEG, extracted by the AI service (ffmpeg,
 * about a second) into the record's `thumbnail/` or `frames/` folder on the
 * media disk. Used for "use current frame" and for adding a key frame.
 */
class FrameCapture
{
    public function __construct(private AiServiceClient $client) {}

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when the AI service didn't store the frame
     */
    public function capture(Record $record, float $seconds, string $path): void
    {
        $master = $record->files()->where('role', FileRole::Master)->firstOrFail(['id', 'storage_location']);

        $stored = $this->client->captureFrame([
            'record_id' => $record->id,
            'video_path' => $master->storage_location,
            'time_sec' => round($seconds, 3),
            'output_path' => $path,
        ]);

        if ($stored !== $path || ! Storage::disk('media')->exists($path)) {
            throw new RuntimeException('The AI service did not store the frame.');
        }
    }
}
