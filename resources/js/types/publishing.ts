/** YouTube publishing (Phase 7). */
export type PublicationStatus =
    | 'queued'
    | 'uploading'
    | 'processing'
    | 'published'
    | 'failed';

export type YouTubePrivacy = 'private' | 'unlisted' | 'public';

export type Publication = {
    id: number;
    status: PublicationStatus;
    privacy_status: YouTubePrivacy;
    title: string;
    channel_title: string;
    video_id: string | null;
    video_url: string | null;
    /** Upload progress, 0–100. */
    percent: number;
    error: string | null;
    /** Why the custom thumbnail wasn't used (the video is still published). */
    thumbnail_error: string | null;
    youtube_upload_status: string | null;
    published_at: string | null;
    status_checked_at: string | null;
    created_at: string | null;
    published_by: string | null;
};

/** A publication on Settings → YouTube, with the record it came from. */
export type PublicationWithRecord = Publication & {
    record: { id: string; title: string } | null;
};

export type YouTubeChannel = {
    title: string;
    url: string;
    thumbnail_url: string | null;
    connected_at?: string | null;
};

export type SelectOption = { value: string; label: string };

/** The workspace's Publish panel. */
export type Publishing = {
    /** Whether the server has a Google OAuth client. */
    configured: boolean;
    channel: YouTubeChannel | null;
    /** Why the record may not leave the archive (empty when it may). */
    blockers: string[];
    can_publish: boolean;
    defaults: {
        title: string;
        description: string;
        tags: string[];
        category_id: string;
    };
    thumbnail_usable: boolean;
    has_video: boolean;
    privacy_options: SelectOption[];
    categories: SelectOption[];
    limits: { title: number; description: number; tags: number };
};

export type PublishInput = {
    privacy_status: YouTubePrivacy;
    title: string;
    description: string;
    tags: string[];
    category_id: string;
    made_for_kids: boolean;
    with_thumbnail: boolean;
};

/** On `records.{id}`: a publication was queued, uploaded further, finished or failed. */
export type RecordPublicationUpdatedEvent = {
    publication_id: number;
    status: PublicationStatus;
    percent: number;
    error: string | null;
};
