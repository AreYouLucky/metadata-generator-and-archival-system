import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy } from '@/routes/records';

/**
 * Soft-delete a record (kept for the audit log). Processing still running is
 * cancelled first. The caller navigates away on success.
 */
export function useDeleteRecord(recordId: string) {
    return useMutation({
        mutationFn: async () =>
            (await http.delete<{ deleted: boolean }>(destroy.url(recordId)))
                .data,
    });
}
