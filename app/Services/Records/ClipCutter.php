<?php

namespace App\Services\Records;

use App\Enums\TaskStatus;
use App\Events\RecordClipUpdated;
use App\Jobs\StartClipJob;
use App\Models\Clip;
use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Clips (Phase 7): a part of the record's master cut to its own MP4 by the
 * AI service, which also checksums and probes it. This class queues the cut,
 * records the result and tells the workspace.
 */
class ClipCutter
{
    /**
     * The probe fields kept with a clip (the Clips panel shows them).
     */
    private const array TECHNICAL_FIELDS = [
        'file_format', 'duration', 'bitrate', 'video_codec', 'video_profile', 'resolution',
        'frame_rate', 'aspect_ratio', 'audio_codec', 'audio_channels', 'audio_sample_rate',
    ];

    public function create(Record $record, User $user, float $start, float $end, ?string $title): Clip
    {
        $clip = new Clip(['title' => $title, 'start' => round($start, 3), 'end' => round($end, 3)]);
        $clip->record_id = $record->id;
        $clip->created_by = $user->id;
        $clip->status = TaskStatus::Queued;
        $clip->save();

        StartClipJob::dispatch($clip);

        return $clip;
    }

    /**
     * The AI service accepted the cut.
     */
    public function started(Clip $clip): void
    {
        if ($clip->status === TaskStatus::Queued) {
            $clip->status = TaskStatus::Processing;
            $clip->save();
            $this->broadcast($clip);
        }
    }

    /**
     * The cut file with its checksums and probe summary.
     *
     * @param  array<string, mixed>  $probe
     */
    public function done(Clip $clip, int $fileSize, string $md5, string $sha256, array $probe): void
    {
        $clip->forceFill([
            'status' => TaskStatus::Done,
            'path' => $clip->storagePath(),
            'file_size' => $fileSize,
            'md5_checksum' => $md5,
            'sha256_checksum' => $sha256,
            'technical' => array_intersect_key($probe, array_flip(self::TECHNICAL_FIELDS)),
            'error' => null,
            'finished_at' => now(),
        ])->save();

        $this->broadcast($clip);
    }

    public function failed(Clip $clip, string $error): void
    {
        $clip->forceFill([
            'status' => TaskStatus::Failed,
            'error' => mb_substr($error, 0, 500),
            'finished_at' => now(),
        ])->save();

        $this->broadcast($clip);
    }

    /**
     * Remove the clip and its file.
     */
    public function delete(Clip $clip): void
    {
        Storage::disk('media')->delete($clip->storagePath());
        $clip->delete();
    }

    /**
     * The record's clips for the Clips panel, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function list(Record $record): array
    {
        return array_values($record->clips()
            ->latest('id')
            ->limit((int) config('archive.clips.max_per_record'))
            ->get(['id', 'record_id', 'title', 'start', 'end', 'status', 'file_size', 'sha256_checksum', 'error', 'technical', 'created_at'])
            ->map(fn (Clip $clip): array => $clip->present())
            ->all());
    }

    private function broadcast(Clip $clip): void
    {
        rescue(fn () => RecordClipUpdated::dispatch($clip), report: false);
    }
}
