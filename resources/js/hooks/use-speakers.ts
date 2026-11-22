import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store, update } from '@/routes/records/speakers';
import { speaker as assignSpeakerRoute } from '@/routes/records/transcript';
import type { Speaker } from '@/types/records';

/**
 * Add a speaker (labelled S1, S2… by the server). The new speaker is appended
 * to the page's `speakers` prop, so no reload is needed.
 */
export function useCreateSpeaker(recordId: string) {
    return useMutation({
        mutationFn: async (displayName: string | null = null) =>
            (
                await http.post<Speaker>(store.url(recordId), {
                    display_name: displayName,
                })
            ).data,
        onSuccess: (speaker) => router.appendToProp('speakers', speaker),
    });
}

/**
 * Rename a speaker; an empty name falls back to its label.
 */
export function useUpdateSpeaker(recordId: string) {
    return useMutation({
        mutationFn: async ({
            speakerId,
            displayName,
        }: {
            speakerId: number;
            displayName: string;
        }) =>
            (
                await http.patch<Speaker>(
                    update.url({ record: recordId, speaker: speakerId }),
                    { display_name: displayName },
                )
            ).data,
        onSuccess: (saved) =>
            router.replaceProp('speakers', (current: unknown) =>
                (current as Speaker[]).map((speaker) =>
                    speaker.id === saved.id ? saved : speaker,
                ),
            ),
    });
}

/**
 * Give many lines the same speaker in one request ("apply to all"); null
 * clears it. The workspace updates the lines in its own state.
 */
export function useAssignSpeaker(recordId: string) {
    return useMutation({
        mutationFn: async ({
            segmentIds,
            speakerId,
        }: {
            segmentIds: number[];
            speakerId: number | null;
        }) =>
            (
                await http.patch<{ updated: number }>(
                    assignSpeakerRoute.url(recordId),
                    { segment_ids: segmentIds, speaker_id: speakerId },
                )
            ).data,
    });
}
