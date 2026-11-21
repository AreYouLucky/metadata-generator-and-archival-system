import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { update } from '@/routes/records/segments';

type UpdatedSegment = {
    id: number;
    text: string;
    is_edited: boolean;
    speaker_id: number | null;
};

/**
 * Save a transcript line's text and/or speaker (`speakerId: null` clears it).
 * The workspace keeps the lines in state, so there is no query to invalidate.
 */
export function useUpdateSegment(recordId: string) {
    return useMutation({
        mutationFn: async ({
            segmentId,
            text,
            speakerId,
        }: {
            segmentId: number;
            text?: string;
            speakerId?: number | null;
        }) =>
            (
                await http.patch<UpdatedSegment>(
                    update.url({ record: recordId, segment: segmentId }),
                    {
                        ...(text === undefined ? {} : { text }),
                        ...(speakerId === undefined
                            ? {}
                            : { speaker_id: speakerId }),
                    },
                )
            ).data,
    });
}
