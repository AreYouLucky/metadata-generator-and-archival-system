import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy, store } from '@/routes/records/clips';
import type { Clip } from '@/types/clips';

const reloadClips = () => router.reload({ only: ['clips'] });

/**
 * Cut a clip from the master. Queued: progress arrives over Reverb
 * (`record.clip-updated`, see useRecordChannel), which reloads the list.
 */
export function useCreateClip(recordId: string) {
    return useMutation({
        mutationFn: async (clip: {
            start: number;
            end: number;
            title: string | null;
        }) =>
            (await http.post<{ clip: Clip }>(store.url(recordId), clip)).data
                .clip,
        onSuccess: reloadClips,
    });
}

/** Delete a finished or failed clip and its file. */
export function useDeleteClip(recordId: string) {
    return useMutation({
        mutationFn: async (clipId: number) => {
            await http.delete(destroy.url({ record: recordId, clip: clipId }));
        },
        onSuccess: reloadClips,
    });
}
