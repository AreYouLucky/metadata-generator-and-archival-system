/**
 * The workspace dashboard (`GET /dashboard`), built by
 * App\Services\Dashboard\WorkspaceDashboard.
 */
import type { ProcessingStatus, ReviewStatus } from '@/types/records';

export type DashboardStats = {
    total: number;
    added_this_week: number;
    duration_seconds: number;
    review: Record<ReviewStatus, number>;
    processing: Record<ProcessingStatus, number>;
};

export type DashboardActivityDay = {
    date: string;
    count: number;
};

export type DashboardAttentionItem = {
    id: string;
    title: string;
    reason: 'failed' | 'changes_requested';
    updated_at: string | null;
    creator: string | null;
};

export type DashboardProcessingItem = {
    id: string;
    title: string;
    processing_status: ProcessingStatus;
    /** The stage running now; null while waiting to start. */
    stage: string | null;
    progress: number;
    created_at: string | null;
    creator: string | null;
};

export type DashboardDraftItem = {
    id: string;
    title: string;
    project: string | null;
    updated_at: string | null;
    creator: string | null;
};

export type DashboardProjectItem = {
    id: string;
    title: string;
    records_count: number;
    last_added_at: string | null;
};

/** Today's transcription minutes; null for admins, who have no limit. */
export type DashboardAllowance = {
    limit: number;
    used: number;
    remaining: number;
} | null;
