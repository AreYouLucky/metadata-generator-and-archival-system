/**
 * Batches (Phase 6): several uploads or web addresses processed together.
 */
import type {
    ProcessingStatus,
    ReviewStatus,
    StageState,
} from '@/types/records';

export type BatchStatus = 'processing' | 'done';

export type BatchZipStatus = 'building' | 'ready' | 'failed';

export type Batch = {
    id: string;
    name: string;
    status: BatchStatus;
    total: number;
    completed: number;
    failed: number;
    created_at: string | null;
    zip_status: BatchZipStatus | null;
    zip_built_at: string | null;
};

export type BatchRecord = {
    id: string;
    title: string;
    accession_number: string | null;
    /** Host of the web address, for URL imports. */
    source_host: string | null;
    processing_status: ProcessingStatus;
    processing_error: string | null;
    review_status: ReviewStatus;
    stages: StageState[];
    /** Why the record stays out of the ZIP; empty when it goes in. */
    zip_exclusions: string[];
};

/** Reverb payload on the private `batches.{id}` channel. */
export type BatchProgressEvent = Pick<
    Batch,
    'status' | 'total' | 'completed' | 'failed' | 'zip_status'
>;
