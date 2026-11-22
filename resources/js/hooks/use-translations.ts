import { router } from '@inertiajs/react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { update as updateSegment } from '@/routes/records/segments';
import { show, store } from '@/routes/records/translations';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Translation, TranslationLines } from '@/types/records';

/**
 * Translate the transcript into a language (or again). Progress arrives over
 * Reverb (`record.translation-updated`).
 */
export function useStartTranslation(recordId: string) {
    return useMutation({
        mutationFn: async (language: string) =>
            (
                await http.post<{ translation: Translation }>(
                    store.url(recordId),
                    { language },
                )
            ).data.translation,
    });
}

export function translationLinesKey(recordId: string, language: string) {
    return ['records', recordId, 'translations', language] as const;
}

/**
 * A finished translation's lines, for the viewer. `version` (the line count
 * and status) makes a re-translation fetch the new lines.
 */
export function useTranslationLines(
    recordId: string,
    language: string,
    enabled: boolean,
) {
    return useQuery({
        queryKey: translationLinesKey(recordId, language),
        queryFn: async () =>
            (
                await http.get<TranslationLines>(
                    show.url({ record: recordId, language }),
                )
            ).data,
        enabled,
        staleTime: 5 * 60_000,
    });
}

/**
 * Correct one translated line (the same endpoint as transcript lines; the
 * machine translation stays as the line's original). Updates the viewer's
 * lines in place, reloads the caption tracks and the edited-line count.
 */
export function useUpdateTranslationLine(recordId: string, language: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({
            segmentId,
            text,
        }: {
            segmentId: number;
            text: string;
        }) =>
            (
                await http.patch<{
                    id: number;
                    text: string;
                    is_edited: boolean;
                }>(
                    updateSegment.url({ record: recordId, segment: segmentId }),
                    { text },
                )
            ).data,
        onSuccess: (saved) => {
            queryClient.setQueryData<TranslationLines>(
                translationLinesKey(recordId, language),
                (current) =>
                    current && {
                        ...current,
                        segments: current.segments.map((segment) =>
                            segment.id === saved.id
                                ? { ...segment, ...saved }
                                : segment,
                        ),
                    },
            );
            useWorkspaceStore.getState().bumpCaptions();
            router.reload({ only: ['translations'] });
        },
    });
}
