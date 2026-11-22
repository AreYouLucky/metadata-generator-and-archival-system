<?php

namespace App\Services\YouTube;

use App\Enums\YouTubePrivacy;
use App\Models\Record;
use App\Models\User;
use App\Models\YoutubePublication;
use App\Services\Catalogue\Distribution;

/**
 * What the workspace's Publish panel shows: the user's connected channel,
 * whether the record may leave the archive (and why not), the starting
 * title/description/tags, and every earlier publication with its status.
 * Tokens and upload session addresses never leave the server.
 *
 * @phpstan-type PublicationRow array{id: int, status: string, privacy_status: string, title: string, channel_title: string, video_id: string|null, video_url: string|null, percent: int, error: string|null, thumbnail_error: string|null, youtube_upload_status: string|null, published_at: string|null, status_checked_at: string|null, created_at: string|null, published_by: string|null}
 */
class PublishPanel
{
    public const int HISTORY_LIMIT = 20;

    public function __construct(
        private YouTubeDraft $draft,
        private Distribution $distribution,
        private YouTubeOAuth $oauth,
    ) {}

    /**
     * @return array{configured: bool, channel: array{title: string, url: string, thumbnail_url: string|null}|null, blockers: list<string>, can_publish: bool, defaults: array{title: string, description: string, tags: list<string>, category_id: string}, thumbnail_usable: bool, has_video: bool, privacy_options: list<array{value: string, label: string}>, categories: list<array{value: string, label: string}>, limits: array{title: int, description: int, tags: int}}
     */
    public function present(Record $record, User $user): array
    {
        $account = $user->youtubeAccount()->first(['id', 'user_id', 'channel_id', 'channel_title', 'channel_thumbnail_url']);
        $blockers = $this->distribution->blockers($record);

        return [
            'configured' => $this->oauth->isConfigured(),
            'channel' => $account === null ? null : [
                'title' => $account->channel_title,
                'url' => $account->channelUrl(),
                'thumbnail_url' => $account->channel_thumbnail_url,
            ],
            'blockers' => $blockers,
            'can_publish' => $blockers === [] && $user->can('publish', $record),
            'defaults' => [
                ...$this->draft->defaults($record),
                'category_id' => (string) config('archive.youtube.default_category_id'),
            ],
            'thumbnail_usable' => $this->draft->thumbnailUsable($record),
            'has_video' => $this->draft->sourceFile($record) !== null,
            'privacy_options' => YouTubePrivacy::options(),
            'categories' => YouTubeDraft::categoryOptions(),
            'limits' => [
                'title' => YouTubeDraft::TITLE_MAX,
                'description' => YouTubeDraft::DESCRIPTION_MAX,
                'tags' => YouTubeDraft::TAGS_MAX_TOTAL,
            ],
        ];
    }

    /**
     * The record's publications, newest first.
     *
     * @return list<PublicationRow>
     */
    public static function publications(Record $record): array
    {
        return array_values($record->youtubePublications()
            ->with('user:id,name')
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['id', 'record_id', 'user_id', 'status', 'privacy_status', 'title', 'channel_title', 'video_id', 'file_size', 'bytes_uploaded', 'error', 'thumbnail_error', 'youtube_upload_status', 'published_at', 'status_checked_at', 'created_at'])
            ->map(fn (YoutubePublication $publication): array => self::row($publication))
            ->all());
    }

    /**
     * @return PublicationRow
     */
    public static function row(YoutubePublication $publication): array
    {
        return [
            'id' => $publication->id,
            'status' => $publication->status->value,
            'privacy_status' => $publication->privacy_status->value,
            'title' => $publication->title,
            'channel_title' => $publication->channel_title,
            'video_id' => $publication->video_id,
            'video_url' => $publication->videoUrl(),
            'percent' => $publication->uploadPercent(),
            'error' => $publication->error,
            'thumbnail_error' => $publication->thumbnail_error,
            'youtube_upload_status' => $publication->youtube_upload_status,
            'published_at' => $publication->published_at?->toIso8601String(),
            'status_checked_at' => $publication->status_checked_at?->toIso8601String(),
            'created_at' => $publication->created_at?->toIso8601String(),
            'published_by' => $publication->user?->name,
        ];
    }
}
