import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store } from '@/routes/records/titles';

/**
 * "Generate more" titles for the active generation. Queued: the new options
 * arrive over Reverb (`record.titles-generated`, see useRecordChannel).
 */
export function useGenerateTitles(recordId: string) {
    return useMutation({
        mutationFn: async (metadataId: number) =>
            (
                await http.post<{ queued: boolean }>(store.url(recordId), {
                    metadata_id: metadataId,
                })
            ).data,
    });
}
