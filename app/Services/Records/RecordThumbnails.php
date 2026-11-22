<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\FileRole;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\User;
use App\Models\VideoAuditLog;
use App\Services\AiService\AiServiceClient;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The record's thumbnail: one of its key frames, a frame captured from the
 * master at any time ("use current frame"), or an uploaded image. Each is
 * stored as its own copy under `records/{id}/thumbnail/` on the private media
 * disk, so re-running the pipeline (which replaces the frames) never loses
 * it. Served only through the authorised thumbnail routes.
 */
class RecordThumbnails
{
    /** Faces in a frame preferred for the default thumbnail. */
    private const int FEW_FACES = 3;

    public function __construct(private AiServiceClient $client) {}

    /**
     * Use a key frame. `$by` is null for the default picked after processing
     * (not logged; the encoder didn't choose it).
     */
    public function useFrame(Record $record, RecordFrame $frame, ?User $by): void
    {
        $disk = $this->disk();

        if (! $disk->exists($frame->path)) {
            throw new RuntimeException('The frame image is missing.');
        }

        $path = $this->newPath($record, 'jpg');
        $disk->copy($frame->path, $path);

        $this->replace($record, $path, $frame->id, $frame->time_sec, $by, 'key frame at '.$this->clock($frame->time_sec));
    }

    /**
     * Once faces are counted, swap a default thumbnail that shows no one for
     * the earliest key frame after the opening second with a few faces (they
     * fill the frame better than a crowd), else the earliest with any. A
     * thumbnail an encoder chose, or uploaded, is never touched.
     */
    public function preferFrameWithFaces(Record $record): void
    {
        $record->refresh();

        if (! $record->thumbnail_is_default) {
            return;
        }

        $withFaces = $record->frames()
            ->where('face_count', '>', 0)
            ->where('time_sec', '>=', 1.0)
            ->get(['id', 'record_id', 'time_sec', 'path', 'face_count']);

        if ($withFaces->isEmpty() || $withFaces->contains('id', $record->thumbnail_frame_id)) {
            return;
        }

        $frame = $withFaces->first(fn (RecordFrame $frame): bool => $frame->face_count <= self::FEW_FACES) ?? $withFaces->first();

        rescue(fn () => $this->useFrame($record, $frame, null));
    }

    /**
     * Capture the master at `$seconds` through the AI service (a second or so).
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function capture(Record $record, float $seconds, User $by): void
    {
        $master = $record->files()->where('role', FileRole::Master)->firstOrFail(['id', 'storage_location']);
        $path = $this->newPath($record, 'jpg');

        $stored = $this->client->captureFrame([
            'record_id' => $record->id,
            'video_path' => $master->storage_location,
            'time_sec' => round($seconds, 3),
            'output_path' => $path,
        ]);

        if ($stored !== $path || ! $this->disk()->exists($path)) {
            throw new RuntimeException('The AI service did not store the frame.');
        }

        $this->replace($record, $path, null, round($seconds, 3), $by, 'frame captured at '.$this->clock($seconds));
    }

    public function upload(Record $record, UploadedFile $image, User $by): void
    {
        $path = $this->newPath($record, strtolower($image->extension() ?: 'jpg'));
        $this->disk()->putFileAs(dirname($path), $image, basename($path));

        $this->replace($record, $path, null, null, $by, 'uploaded image');
    }

    /**
     * The thumbnail as the workspace shows it, or null.
     *
     * @return array{url: string, frame_id: int|null, time_sec: float|null}|null
     */
    public function present(Record $record): ?array
    {
        if ($record->thumbnail_path === null) {
            return null;
        }

        return [
            'url' => route('records.thumbnail', ['record' => $record, 'v' => substr(md5($record->thumbnail_path), 0, 8)]),
            'frame_id' => $record->thumbnail_frame_id,
            'time_sec' => $record->thumbnail_time_sec,
        ];
    }

    private function replace(Record $record, string $path, ?int $frameId, ?float $seconds, ?User $by, string $what): void
    {
        $previous = $record->thumbnail_path;

        $record->thumbnail_path = $path;
        $record->thumbnail_frame_id = $frameId;
        $record->thumbnail_time_sec = $seconds;
        $record->thumbnail_is_default = $by === null;
        $record->save();

        // Only our own copies are removed, never anything else on the disk.
        if ($previous !== null && $previous !== $path && str_starts_with($previous, "records/{$record->id}/thumbnail/")) {
            $this->disk()->delete($previous);
        }

        if ($by !== null) {
            VideoAuditLog::create([
                'record_id' => $record->id,
                'record_file_id' => null,
                'action' => AuditAction::Updated,
                'performed_by' => $by->id,
                'performed_at' => now(),
                'remarks' => "Thumbnail: {$what}.",
            ]);
        }
    }

    private function newPath(Record $record, string $extension): string
    {
        return "records/{$record->id}/thumbnail/".Str::lower(Str::random(20)).".{$extension}";
    }

    /**
     * "1:05" or "1:02:05", as the workspace shows times.
     */
    private function clock(float $seconds): string
    {
        $total = (int) floor(max(0.0, $seconds));

        return $total >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($total, 3600), intdiv($total % 3600, 60), $total % 60)
            : sprintf('%d:%02d', intdiv($total, 60), $total % 60);
    }

    private function disk(): Filesystem
    {
        return Storage::disk('media');
    }
}
