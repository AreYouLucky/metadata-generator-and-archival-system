import { useMutation, useQueryClient } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { update } from '@/routes/records/segments';

type UpdatedSegment = {
    id: number;
    start: number;
    end: number;
    text: string;
    is_edited: boolean;
    speaker_id: number | null;
};

/**
 * Save a transcript line's text, speaker (`speakerId: null` clears it) and/or
 * timing (start and end together). The workspace keeps the lines in state;
 * new timing also moves the translated lines, so their queries refetch.
 */
export function useUpdateSegment(recordId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        onSuccess: (_saved, { timing }) => {
            if (timing) {
                void queryClient.invalidateQueries({
                    queryKey: ['records', recordId, 'translations'],
                });
            }
        },
        mutationFn: async ({
            segmentId,
            text,
            speakerId,
            timing,
        }: {
            segmentId: number;
            text?: string;
            speakerId?: number | null;
            timing?: { start: number; end: number };
        }) =>
            (
                await http.patch<UpdatedSegment>(
                    update.url({ record: recordId, segment: segmentId }),
                    {
                        ...(text === undefined ? {} : { text }),
                        ...(speakerId === undefined
                            ? {}
                            : { speaker_id: speakerId }),
                        ...timing,
                    },
                )
            ).data,
    });
}
