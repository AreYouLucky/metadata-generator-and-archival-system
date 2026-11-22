import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy } from '@/routes/records/segments';

/**
 * Remove a transcript line. The workspace drops it from its own state.
 */
export function useDeleteSegment(recordId: string) {
    return useMutation({
        mutationFn: async (segmentId: number) => {
            await http.delete(
                destroy.url({ record: recordId, segment: segmentId }),
            );

            return segmentId;
        },
    });
}
