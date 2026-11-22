<?php

namespace App\Services\YouTube;

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Events\RecordPublicationUpdated;
use App\Jobs\PublishToYouTubeJob;
use App\Models\Record;
use App\Models\User;
use App\Models\YoutubeAccount;
use App\Models\YoutubePublication;
use App\Services\Catalogue\Distribution;
use App\Services\Records\AuditTrail;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Publishing a record to YouTube (Phase 7). `publish()` saves what the
 * encoder chose and queues the upload. The upload runs in short steps
 * (`step()`), each sending chunks for at most `archive.youtube.step_seconds`
 * and asking YouTube where it got to first, so an interrupted step simply
 * resumes. Every step checks the distribution policy again: a record that is
 * reopened, deleted, classified or embargoed meanwhile stops uploading.
 * Once uploaded, the thumbnail is set and YouTube's processing is followed
 * (`checkStatus()`) until the video is processed, failed or rejected.
 */
class YouTubePublisher
{
    public const string MORE = 'more';

    public const string UPLOADED = 'uploaded';

    public const string STOPPED = 'stopped';

    public function __construct(
        private YouTubeOAuth $oauth,
        private YouTubeClient $client,
        private YouTubeDraft $draft,
        private Distribution $distribution,
        private AuditTrail $audit,
    ) {}

    /**
     * @param  array{privacy_status: string, title: string, description: string|null, tags: list<string>|null, category_id: string|null, made_for_kids: bool, with_thumbnail: bool}  $choices
     */
    public function publish(Record $record, User $user, YoutubeAccount $account, array $choices): YoutubePublication
    {
        $publication = new YoutubePublication([
            ...$choices,
            'description' => (string) ($choices['description'] ?? ''),
            'tags' => $choices['tags'] ?? [],
            'category_id' => $choices['category_id'] ?? (string) config('archive.youtube.default_category_id'),
        ]);
        $publication->record_id = $record->id;
        $publication->user_id = $user->id;
        $publication->youtube_account_id = $account->id;
        $publication->channel_id = $account->channel_id;
        $publication->channel_title = $account->channel_title;
        $publication->status = PublicationStatus::Queued;
        $publication->save();

        PublishToYouTubeJob::dispatch($publication);
        RecordPublicationUpdated::dispatch($publication);

        return $publication;
    }

    /**
     * One upload step: MORE (queue the next), UPLOADED (the video exists on
     * YouTube) or STOPPED (finished, failed or no longer allowed).
     */
    public function step(YoutubePublication $publication): string
    {
        $publication->refresh();

        if (! in_array($publication->status, [PublicationStatus::Queued, PublicationStatus::Uploading], true)) {
            return self::STOPPED;
        }

        $record = $publication->record()->first();
        $refusal = $this->refusal($publication, $record);

        if ($refusal !== null) {
            $this->fail($publication, $refusal);

            return self::STOPPED;
        }

        /** @var Record $record */
        /** @var YoutubeAccount $account */
        $account = $publication->account()->first();
        $file = $this->draft->sourceFile($record);
        $disk = $this->disk();

        if ($file === null || ! $disk->exists($file->storage_location)) {
            $this->fail($publication, 'The video file is missing from the archive.');

            return self::STOPPED;
        }

        $token = $this->oauth->accessToken($account);
        $mimeType = $file->mime_type ?: 'application/octet-stream';

        if ($publication->upload_session_url === null) {
            $size = (int) $disk->size($file->storage_location);
            $publication->upload_session_url = $this->client->startUpload($token, $publication, $size, $mimeType);
            $publication->file_size = $size;
            $publication->bytes_uploaded = 0;
            $publication->status = PublicationStatus::Uploading;
            $publication->error = null;
            $publication->save();
            RecordPublicationUpdated::dispatch($publication);
        } else {
            // A step may have died mid-chunk: ask YouTube how far it got.
            try {
                $progress = $this->client->uploadStatus($token, $publication->upload_session_url, (int) $publication->file_size);
            } catch (YouTubeException $exception) {
                if ($exception->reason !== 'sessionExpired') {
                    throw $exception;
                }

                $publication->upload_session_url = null;
                $publication->bytes_uploaded = 0;
                $publication->save();

                return self::MORE;
            }

            if ($progress['done']) {
                $this->uploaded($publication, $record, (string) $progress['video_id'], $progress['upload_status']);

                return self::UPLOADED;
            }

            $publication->bytes_uploaded = $progress['received'];
        }

        return $this->sendChunks($publication, $record, $token, $file->storage_location, $mimeType);
    }

    /**
     * Follow YouTube's processing. True once there is nothing more to wait
     * for (processed, failed, rejected or gone).
     */
    public function checkStatus(YoutubePublication $publication): bool
    {
        $publication->refresh();
        $account = $publication->account()->first();

        if ($publication->video_id === null || $account === null || ! in_array($publication->status, [PublicationStatus::Processing, PublicationStatus::Published], true)) {
            return true;
        }

        $status = $this->client->videoStatus($this->oauth->accessToken($account), $publication->video_id);
        $publication->status_checked_at = now();

        if ($status === null) {
            $publication->status = PublicationStatus::Failed;
            $publication->error = 'The video is no longer on YouTube.';
            $publication->save();
            RecordPublicationUpdated::dispatch($publication);

            return true;
        }

        $publication->youtube_upload_status = $status['upload_status'];
        $publication->youtube_processing_status = $status['processing_status'];
        $final = true;

        match ($status['upload_status']) {
            'processed' => $this->markPublished($publication),
            'failed' => $this->markFailed($publication, 'YouTube could not process the video'.($status['reason'] ? " ({$status['reason']})." : '.')),
            'rejected' => $this->markFailed($publication, 'YouTube rejected the video'.($status['reason'] ? " ({$status['reason']})." : '.')),
            'deleted' => $this->markFailed($publication, 'The video was deleted on YouTube.'),
            default => $final = false,
        };

        $publication->save();
        RecordPublicationUpdated::dispatch($publication);

        return $final;
    }

    /**
     * Upload the record's thumbnail as the video's custom thumbnail. Not
     * fatal: YouTube allows custom thumbnails only on verified channels, so a
     * refusal is noted on the publication and the video stays published.
     */
    public function setThumbnail(YoutubePublication $publication): void
    {
        $publication->refresh();
        $account = $publication->account()->first();
        $record = $publication->record()->first();

        if (! $publication->with_thumbnail || $publication->video_id === null || $account === null || $record === null) {
            return;
        }

        if (! $this->draft->thumbnailUsable($record)) {
            $publication->thumbnail_error = 'The record has no JPEG or PNG thumbnail of 2 MB or less, so YouTube chose one.';
        } else {
            $path = (string) $record->thumbnail_path;

            try {
                $this->client->setThumbnail($this->oauth->accessToken($account), $publication->video_id, (string) $this->disk()->get($path), (string) $this->disk()->mimeType($path));
                $publication->thumbnail_error = null;
            } catch (YouTubeException $exception) {
                if ($exception->transient) {
                    throw $exception;
                }

                $publication->thumbnail_error = $exception->reason === 'forbidden'
                    ? 'YouTube allows custom thumbnails only on verified channels, so it chose one.'
                    : mb_substr($exception->getMessage(), 0, 500);
            }
        }

        $publication->save();
        RecordPublicationUpdated::dispatch($publication);
    }

    public function fail(YoutubePublication $publication, string $message): void
    {
        $publication->status = PublicationStatus::Failed;
        $publication->error = mb_substr($message, 0, 1000);
        $publication->upload_session_url = null;
        $publication->save();

        RecordPublicationUpdated::dispatch($publication);
    }

    /**
     * Why this publication may not go on; null when it may.
     */
    private function refusal(YoutubePublication $publication, ?Record $record): ?string
    {
        if ($record === null) {
            return 'The record was deleted.';
        }

        $blockers = $this->distribution->blockers($record);

        if ($blockers !== []) {
            return 'This record may no longer leave the archive: '.implode(' ', $blockers);
        }

        if ($publication->youtube_account_id === null) {
            return 'The YouTube channel was disconnected.';
        }

        return null;
    }

    private function sendChunks(YoutubePublication $publication, Record $record, string $token, string $path, string $mimeType): string
    {
        $size = (int) $publication->file_size;
        $chunkBytes = max(1, (int) config('archive.youtube.chunk_mb')) * 1024 * 1024;
        $deadline = microtime(true) + max(5, (int) config('archive.youtube.step_seconds'));
        $stream = $this->disk()->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Could not read the video file.');
        }

        try {
            $this->seek($stream, $publication->bytes_uploaded);

            while ($publication->bytes_uploaded < $size) {
                $offset = $publication->bytes_uploaded;
                $bytes = (string) stream_get_contents($stream, min($chunkBytes, $size - $offset));

                if ($bytes === '') {
                    throw new RuntimeException('The video file ended early; it may have changed during the upload.');
                }

                $progress = $this->client->uploadChunk($token, (string) $publication->upload_session_url, $bytes, $offset, $size, $mimeType);

                if ($progress['done']) {
                    $this->uploaded($publication, $record, (string) $progress['video_id'], $progress['upload_status']);

                    return self::UPLOADED;
                }

                $publication->bytes_uploaded = $progress['received'];
                $publication->saveQuietly();

                // YouTube kept less than was sent: carry on from what it has.
                if ($progress['received'] !== $offset + strlen($bytes)) {
                    $this->seek($stream, $progress['received']);
                }

                if (microtime(true) >= $deadline) {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }

        $publication->save();
        RecordPublicationUpdated::dispatch($publication);

        return self::MORE;
    }

    private function uploaded(YoutubePublication $publication, Record $record, string $videoId, ?string $uploadStatus): void
    {
        DB::transaction(function () use ($publication, $record, $videoId, $uploadStatus): void {
            $publication->video_id = mb_substr($videoId, 0, 32);
            $publication->youtube_upload_status = $uploadStatus;
            $publication->bytes_uploaded = (int) $publication->file_size;
            $publication->upload_session_url = null;
            $publication->status = PublicationStatus::Processing;
            $publication->save();

            $this->audit->log(
                $record,
                AuditAction::Published,
                $publication->user()->first(),
                "YouTube video {$publication->video_id} on {$publication->channel_title} ({$publication->privacy_status->value}).",
            );
        });

        RecordPublicationUpdated::dispatch($publication);
    }

    private function markPublished(YoutubePublication $publication): void
    {
        $publication->status = PublicationStatus::Published;
        $publication->published_at ??= now();
        $publication->error = null;
    }

    private function markFailed(YoutubePublication $publication, string $message): void
    {
        $publication->status = PublicationStatus::Failed;
        $publication->error = mb_substr($message, 0, 1000);
    }

    /**
     * Move a read stream to `$offset`: seek when possible, otherwise read past.
     *
     * @param  resource  $stream
     */
    private function seek($stream, int $offset): void
    {
        if (stream_get_meta_data($stream)['seekable'] && fseek($stream, $offset) === 0) {
            return;
        }

        $position = (int) ftell($stream);

        if ($position > $offset) {
            throw new RuntimeException('Cannot rewind the video stream.');
        }

        while ($position < $offset && ! feof($stream)) {
            $read = strlen((string) fread($stream, max(1, min(1024 * 1024, $offset - $position))));
            $position += $read;

            if ($read === 0) {
                break;
            }
        }
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('media');
    }
}
