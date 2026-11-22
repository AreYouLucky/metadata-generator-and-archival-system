import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store as reviewStore } from '@/routes/admin/approvals';
import { reopen } from '@/routes/admin/records';
import { submit } from '@/routes/records';
import type { ReviewStatus } from '@/types/records';
import type { ReviewDecision } from '@/types/reviews';

type ReviewResponse = { review_status: ReviewStatus; message?: string };

/** Workspace props that change with the review status. */
const WORKSPACE_PROPS = ['record', 'review'];

/**
 * Send a draft (or a record with changes requested) for approval. It is
 * read-only for the encoder afterwards, so the workspace reloads.
 */
export function useSubmitRecord(recordId: string) {
    return useMutation({
        mutationFn: async (input: {
            notes: string;
            /** Confirm pending AI subject and keyword suggestions too. */
            confirmSuggestions: boolean;
        }) =>
            (
                await http.post<ReviewResponse>(submit.url(recordId), {
                    notes: input.notes.trim() || null,
                    confirm_suggestions: input.confirmSuggestions,
                })
            ).data,
        onSuccess: () =>
            router.reload({ only: [...WORKSPACE_PROPS, 'archive'] }),
    });
}

/**
 * An admin's decision on a submitted record; notes are required unless
 * approving.
 */
export function useReviewRecord(recordId: string) {
    return useMutation({
        mutationFn: async (input: { action: ReviewDecision; notes: string }) =>
            (
                await http.post<ReviewResponse>(reviewStore.url(recordId), {
                    action: input.action,
                    notes: input.notes.trim() || null,
                })
            ).data,
    });
}

/**
 * Back to draft (and off the public site) for an approved or rejected record.
 */
export function useReopenRecord(recordId: string, reloadProps?: string[]) {
    return useMutation({
        mutationFn: async (notes: string) =>
            (
                await http.post<ReviewResponse>(reopen.url(recordId), {
                    notes: notes.trim() || null,
                })
            ).data,
        onSuccess: () => router.reload({ only: reloadProps }),
    });
}
