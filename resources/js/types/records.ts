/**
 * Record, workspace and pipeline types. The `AiMetadata` shape mirrors the
 * `ai` block of PROJECT_SPEC §10 and ai-service/app/schemas/metadata.py.
 */
import type { SuggestedLocation } from '@/types/archive';

export type ProcessingStatus =
    | 'queued'
    | 'processing'
    | 'ready'
    | 'failed'
    | 'cancelled';

export type ReviewStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'rejected'
    | 'changes_requested';

export type StageStatus = 'pending' | 'running' | 'done' | 'failed' | 'skipped';

export type PipelineStageName =
    | 'checksum'
    | 'probe'
    | 'extract_audio'
    | 'transcribe'
    | 'generate_metadata'
    | 'proxy';

export type PresetName =
    | 'generic'
    | 'youtube'
    | 'tiktok'
    | 'instagram'
    | 'linkedin';

export type RecordListItem = {
    id: string;
    title: string;
    processing_status: ProcessingStatus;
    review_status: ReviewStatus;
    accession_number: string | null;
    classification: string;
    /** Set while an embargo is in force. */
    embargo_until: string | null;
    duration: number | null;
    project: string | null;
    series: string | null;
    created_at: string | null;
    creator: string | null;
};

export type RecordFilters = {
    q: string;
    processing_status: ProcessingStatus | null;
    review_status: ReviewStatus | null;
    flag: 'embargoed' | 'classified' | null;
    sort: 'newest' | 'oldest' | 'title';
};

export type PresetRules = {
    name: PresetName;
    label: string;
    title_max: number | null;
    description_max: number;
    tags_max_count: number | null;
    tags_max_chars: number | null;
    hashtags_min: number;
    hashtags_max: number;
    chapters: boolean;
    guidance: string;
};

export type WorkspaceRecord = {
    id: string;
    title: string;
    project: { id: string; title: string } | null;
    series: string | null;
    language: string | null;
    language_confidence: number | null;
    processing_status: ProcessingStatus;
    processing_error: string | null;
    review_status: ReviewStatus;
    accession_number: string | null;
    classification: string;
    /** Set while an embargo is in force. */
    embargo_until: string | null;
    created_at: string | null;
    can_edit: boolean;
};

export type WorkspaceMedia = {
    url: string;
    mime_type: string | null;
    /** null until the probe stage has run; true when the master or its access copy plays. */
    playable: boolean | null;
    /** What the player streams: the master, or the access copy made for browsers. */
    source: 'master' | 'proxy';
    /** The master's frame rate ("25/1") and start timecode, for timecode display. */
    frame_rate: string | null;
    start_timecode: string | null;
    duration: number | null;
    /** Set once the audio stage has written the waveform peaks. */
    has_waveform: boolean;
};

/** records/{id}/waveform.json, written by the AI service's audio stage. */
export type Waveform = {
    version: number;
    peaks_per_second: number;
    /** Peak level per bucket, 0-100. */
    peaks: number[];
};

export type StageState = {
    stage: PipelineStageName;
    label: string;
    status: StageStatus;
    progress: number;
    message: string | null;
};

export type TranscriptSegment = {
    id: number;
    start: number;
    end: number;
    text: string;
    is_edited: boolean;
    speaker_id: number | null;
};

/** A speaker label (S1, S2…) and the name an encoder gave it. */
export type Speaker = {
    id: number;
    label: string;
    display_name: string | null;
};

export type ContributorRole =
    | 'producer'
    | 'director'
    | 'videographer'
    | 'editor'
    | 'presenter'
    | 'cast'
    | 'contributor';

export type ContributorRoleOption = { value: ContributorRole; label: string };

/** A credit; `id` is absent until the row has been saved. */
export type Contributor = {
    id?: number;
    role: ContributorRole;
    name: string;
};

export type WorkspaceTranscript = {
    language: string;
    segments: TranscriptSegment[];
};

export type Chapter = {
    start: number;
    title: string;
};

/** A line worth quoting, aligned to the transcript lines it came from. */
export type Quote = {
    start: number;
    end: number;
    speaker: string | null;
    text: string;
};

export type Tone =
    | 'informative'
    | 'educational'
    | 'formal'
    | 'conversational'
    | 'celebratory'
    | 'inspirational'
    | 'promotional'
    | 'serious'
    | 'urgent'
    | 'entertaining';

export type ContentFlags = {
    profanity: boolean;
    sensitiveTopics: string[];
};

export type AiMetadata = {
    titles: string[];
    selectedTitle: string | null;
    description: string | null;
    summary: string | null;
    tags: string[];
    keywords: string[];
    hashtags: string[];
    chapters: Chapter[];
    quotes: Quote[];
    tone: Tone | null;
    contentFlags: ContentFlags | null;
    suggestedLocation?: SuggestedLocation | null;
};

export type ActiveMetadata = {
    id: number;
    preset: PresetName;
    model: string;
    custom_prompt: string | null;
    created_at: string | null;
    payload: AiMetadata;
};

export type MetadataHistoryItem = {
    id: number;
    preset: PresetName;
    model: string;
    custom_prompt: string | null;
    is_active: boolean;
    created_at: string | null;
};

/** Reverb payloads on the private `records.{id}` channel. */
export type RecordProgressEvent = {
    processing_status: ProcessingStatus;
    stage: Omit<StageState, 'label'>;
};

export type TaskStatus = 'queued' | 'processing' | 'done' | 'failed';

/** A transcript translation (an extra `record_transcripts` row). */
export type Translation = {
    id: number;
    language: string;
    status: TaskStatus;
    progress: number;
    error: string | null;
    line_count: number;
    /** Lines corrected by hand (translating again would replace them). */
    edited_count: number;
};

export type TranslationLanguage = { code: string; name: string };

/** GET /records/{id}/translations/{language} */
export type TranslationLines = {
    language: string;
    segments: Pick<
        TranscriptSegment,
        'id' | 'start' | 'end' | 'text' | 'is_edited'
    >[];
};

export type ExportFormatName = 'json' | 'srt' | 'vtt' | 'txt' | 'csv' | 'md';

export type ExportFormatOption = {
    value: ExportFormatName;
    label: string;
    /** Built from one transcript, so it takes a language. */
    transcript: boolean;
};

export type RecordArchiveUpdatedEvent = { reason: string };

export type RecordTranslationUpdatedEvent = { translation: Translation };

export type RecordTitlesGeneratedEvent = {
    metadata_id: number;
    titles: string[];
    error: string | null;
};

export type RecordStageCompletedEvent = {
    stage: PipelineStageName;
    processing_status: ProcessingStatus;
};
