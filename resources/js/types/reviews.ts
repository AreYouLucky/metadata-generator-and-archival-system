import type { ProcessingStatus } from '@/types/records';

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
