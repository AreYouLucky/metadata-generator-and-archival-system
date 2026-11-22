/** Clips (Phase 7): a part of the master cut to its own MP4. */
export type ClipStatus = 'queued' | 'processing' | 'done' | 'failed';

export type Clip = {
    id: number;
    title: string | null;
    start: number;
    end: number;
    status: ClipStatus;
    file_size: number | null;
    sha256: string | null;
    error: string | null;
    /** Probe summary of the cut file (format, codecs, resolution...). */
    technical: {
        file_format?: string | null;
        duration?: number | null;
        resolution?: string | null;
        video_codec?: string | null;
        audio_codec?: string | null;
        frame_rate?: string | null;
    } | null;
    download_url: string | null;
    created_at: string | null;
};

export type ClipLimits = { min_seconds: number; max_seconds: number };

/** On `records.{id}`: a clip started, was cut, or failed. */
export type RecordClipUpdatedEvent = {
    clip_id: number;
    status: ClipStatus;
    title: string;
    error: string | null;
};
