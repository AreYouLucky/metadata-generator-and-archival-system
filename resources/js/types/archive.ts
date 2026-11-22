/**
 * The workspace's Archive panel (PROJECT_SPEC §9 ArchivePanel, §11). Mirrors
 * App\Services\Records\ArchivePresenter.
 */
import type { LocationValue } from '@/hooks/use-locations';
import type { Scene } from '@/types/visuals';

export type Option = { value: string; label: string };

export type ArchiveDescriptive = {
    title: string;
    alternative_title: string | null;
    description: string | null;
    genre_id: number | null;
    video_type_id: number | null;
    language: string | null;
    country_origin: string | null;
    date_recorded: string | null;
    date_recorded_precision: string | null;
    date_recorded_source: string | null;
    date_published: string | null;
    season_number: number | null;
    episode_number: number | null;
    project_id: string | null;
    project_series_id: string | null;
};

export type ArchiveLocation = LocationValue & {
    location: string | null;
    latitude: number | null;
    longitude: number | null;
    location_source: string | null;
};

export type ArchiveArchival = {
    accession_number: string | null;
    security_classification: string;
    retention_period: string | null;
    archival_value: string | null;
    disposition_date: string | null;
    disposition_action: string | null;
};

export type ArchiveSubject = {
    id: number;
    subject: string;
    type: string;
    source: string;
    is_confirmed: boolean;
};

export type ArchiveKeyword = {
    id: number;
    keyword: string;
    source: string;
    is_confirmed: boolean;
};

export type ArchiveRights = {
    copyright_holder: string | null;
    copyright_year: number | null;
    rights_statement: string | null;
    license: string | null;
    license_url: string | null;
    access_level: string;
    usage_restrictions: string | null;
    territories: string[];
    embargo_until: string | null;
    permission_status: string;
    release_consent: string;
};

export type ArchiveProvenance = {
    source_carrier: string | null;
    carrier_label: string | null;
    carrier_id: string | null;
    transfer_date: string | null;
    transfer_facility: string | null;
    transfer_operator: string | null;
    transfer_deck: string | null;
    transfer_settings: string | null;
    notes: string | null;
};

export type AudioTrack = {
    index: number;
    codec: string | null;
    channels: number | null;
    sampleRate?: number | null;
    language: string | null;
};

export type TechnicalMetadata = {
    file_format: string | null;
    duration: number | null;
    bitrate: number | null;
    start_timecode: string | null;
    video_codec: string | null;
    video_profile: string | null;
    resolution: string | null;
    frame_rate: string | null;
    aspect_ratio: string | null;
    scan_type: string | null;
    bit_depth: number | null;
    chroma_subsampling: string | null;
    color_space: string | null;
    color_primaries: string | null;
    color_transfer: string | null;
    audio_codec: string | null;
    audio_channels: number | null;
    audio_sample_rate: number | null;
    audio_tracks: AudioTrack[];
};

export type ArchiveFile = {
    id: string;
    role: 'master' | 'mezzanine' | 'proxy' | 'access';
    original_filename: string | null;
    mime_type: string | null;
    file_size: number | null;
    md5_checksum: string | null;
    sha256_checksum: string | null;
    checksum_status: 'pending' | 'ok' | 'mismatch';
    created_at: string | null;
    technical: TechnicalMetadata | null;
};

export type ArchiveAttachment = {
    id: string;
    kind: string;
    original_filename: string;
    mime_type: string;
    file_size: number;
    sha256_checksum: string;
    description: string | null;
    created_at: string | null;
};

export type FixityEntry = {
    id: number;
    file_id: string;
    role: string | null;
    algorithm: string;
    matched: boolean | null;
    triggered_by: string;
    checked_at: string;
};

export type AuditEntry = {
    id: number;
    action: string;
    file_id: string | null;
    performed_by: string;
    performed_at: string;
    remarks: string | null;
};

export type WorkPart = {
    id: string;
    title: string;
    part_number: number | null;
    part_label: string | null;
};

export type ArchiveWork = {
    id: string;
    title: string;
    total_parts: number | null;
    parts: WorkPart[];
};

export type Archive = {
    descriptive: ArchiveDescriptive;
    location: ArchiveLocation;
    credits: { agency_id: number | null; division_id: number | null };
    archival: ArchiveArchival;
    subjects: ArchiveSubject[];
    keywords: ArchiveKeyword[];
    rights: ArchiveRights | null;
    provenance: ArchiveProvenance | null;
    files: ArchiveFile[];
    attachments: ArchiveAttachment[];
    work: ArchiveWork | null;
    part_number: number | null;
    part_label: string | null;
    transcripts: { language: string; is_original: boolean; status: string }[];
    /** Shot/scene segments (Phase 5); the shots themselves are in Visuals. */
    segments: { shot_count: number; scenes: Scene[] };
    fixity: FixityEntry[];
    audit: AuditEntry[];
    /** From the master's container tags (hints, not facts). */
    suggestions: {
        date_recorded: string | null;
        latitude: number | null;
        longitude: number | null;
    };
    embargoed: boolean;
};

export type ArchiveOptions = {
    genres: { id: number; name: string }[];
    videoTypes: { id: number; name: string }[];
    datePrecisions: Option[];
    subjectTypes: Option[];
    classifications: Option[];
    archivalValues: Option[];
    dispositionActions: Option[];
    accessLevels: Option[];
    permissionStatuses: Option[];
    releaseConsents: Option[];
    sourceCarriers: Option[];
    attachmentKinds: Option[];
    works: { id: string; title: string; total_parts: number | null }[];
    maxAttachmentMb: number;
};

/** The AI's location suggestion, matched to location ids by the server. */
export type SuggestedLocation = LocationValue & {
    text: string;
    region: string | null;
    province: string | null;
    city: string | null;
    barangay: string | null;
};

/** "Fill with AI" suggestions for Archive → Descriptive (unsaved until Save). */
export type DescriptiveSuggestion = Partial<
    Pick<
        ArchiveDescriptive,
        | 'title'
        | 'alternative_title'
        | 'description'
        | 'genre_id'
        | 'video_type_id'
        | 'country_origin'
        | 'date_recorded'
        | 'date_recorded_precision'
    >
>;

export type RecordDescriptiveSuggestedEvent = {
    fields: DescriptiveSuggestion;
    error: string | null;
};

/** PATCH /records/{id} body: any subset of the catalogue fields. */
export type RecordUpdate = Partial<
    ArchiveDescriptive &
        ArchiveLocation &
        ArchiveArchival & {
            agency_id: number | null;
            division_id: number | null;
            work_id: string | null;
            part_number: number | null;
            part_label: string | null;
        }
>;
