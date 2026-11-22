import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { status, store } from '@/routes/records/youtube';
import { destroy } from '@/routes/youtube';
import type { Publication, PublishInput } from '@/types/publishing';

const reloadPublications = () =>
    router.reload({ only: ['publications', 'publishing'] });

/**
 * Publish the record to the user's YouTube channel. Queued: progress
 * arrives over Reverb (`record.publication-updated`, see useRecordChannel).
 */
export function usePublishToYouTube(recordId: string) {
    return useMutation({
        mutationFn: async (input: PublishInput) =>
            (
                await http.post<{ publication: Publication }>(
                    store.url(recordId),
                    input,
                )
            ).data.publication,
        onSuccess: reloadPublications,
    });
}

/** Ask YouTube again how an uploaded video is doing. */
export function useCheckYouTubeStatus(recordId: string) {
    return useMutation({
        mutationFn: async (publicationId: number) => {
            await http.post(
                status.url({
                    record: recordId,
                    youtubePublication: publicationId,
                }),
            );
        },
    });
}

/** Disconnect the channel (revokes the grant at Google). */
export function useDisconnectYouTube() {
    return useMutation({
        mutationFn: async () => {
            await http.delete(destroy.url());
        },
        onSuccess: () => router.reload({ only: ['channel'] }),
    });
}
