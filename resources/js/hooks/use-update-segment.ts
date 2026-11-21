import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { update } from '@/routes/records/segments';

type UpdatedSegment = { id: number; text: string; is_edited: boolean };

/**
 * Save an edited transcript line. The panel keeps the edit in local state, so
 * there is no query to invalidate.
 */
export function useUpdateSegment(recordId: string) {
    return useMutation({
        mutationFn: async ({
            segmentId,
            text,
        }: {
            segmentId: number;
            text: string;
        }) =>
            (
                await http.patch<UpdatedSegment>(
                    update.url({ record: recordId, segment: segmentId }),
                    { text },
                )
            ).data,
    });
}
