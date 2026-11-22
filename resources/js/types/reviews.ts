import type { ProcessingStatus, ReviewStatus } from '@/types/records';

/** A record waiting in the Pending reviews queue. */
export type PendingReviewItem = {
    id: string;
    title: string;
    project: string | null;
    series: string | null;
    /** Only filled in for admins. */
    creator: string | null;
    processing_status: ProcessingStatus;
    submitted_at: string | null;
};

/** One step of a record's review: submitted, approved, changes requested… */
export type ReviewHistoryItem = {
    id: number;
    action:
        | 'submitted'
        | 'approved'
        | 'changes_requested'
        | 'rejected'
        | 'reopened';
    by: string | null;
    at: string;
    notes: string | null;
};

/** The workspace's approval-workflow state for the signed-in user. */
export type WorkspaceReview = {
    history: ReviewHistoryItem[];
    can_submit: boolean;
    can_review: boolean;
    can_reopen: boolean;
    can_view_access_log: boolean;
    /** Passes the distribution rule: visible on the public catalogue. */
    is_public: boolean;
};

export type ReviewDecision = 'approve' | 'request_changes' | 'reject';

/** A row of the admin review queue. */
export type ApprovalQueueItem = {
    id: string;
    title: string;
    accession_number: string | null;
    project: string | null;
    series: string | null;
    creator: string | null;
    duration: number | null;
    submitted_at: string | null;
};

/** The record as the admin review page shows it. */
export type ApprovalRecord = {
    id: string;
    title: string;
    alternative_title: string | null;
    description: string | null;
    summary: string | null;
    accession_number: string | null;
    project: string | null;
    series: string | null;
    agency: string | null;
    division: string | null;
    genre: string | null;
    video_type: string | null;
    language: string | null;
    language_confidence: number | null;
    date_recorded: string | null;
    date_recorded_precision: string | null;
    place: string | null;
    location: string | null;
    classification: string;
    creator: string | null;
    review_status: ReviewStatus;
    processing_status: ProcessingStatus;
    submitted_at: string | null;
    duration: number | null;
    resolution: string | null;
    contributors: { role: string; name: string }[];
    subjects: string[];
    keywords: string[];
    pending_suggestions: number;
    rights: {
        copyright_holder: string | null;
        license: string | null;
        access_level: string;
        permission_status: string;
        usage_restrictions: string | null;
        embargo_until: string | null;
    } | null;
};

/**
 * People on screen in a record under review (face counts only; nobody is
 * identified), so the reviewer can check consent before approving. Null
 * when faces were never counted.
 */
export type FaceSummary = {
    /** Key frames whose faces were counted. */
    counted_frames: number;
    frames_with_faces: number;
    /** The first frames with faces, in time order. */
    frames: { id: number; time_sec: number; face_count: number; url: string }[];
    /** Whether signed release forms are on file (`available` | `not_available`). */
    release_consent: string;
};
