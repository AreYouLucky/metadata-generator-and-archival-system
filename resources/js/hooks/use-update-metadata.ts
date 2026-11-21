import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { activate, update } from '@/routes/records/metadata';
import type { AiMetadata } from '@/types/records';

/**
 * Save the encoder's edits to the active generation.
 */
export function useUpdateMetadata(recordId: string) {
    return useMutation({
        mutationFn: async ({
            metadataId,
            payload,
        }: {
            metadataId: number;
            payload: AiMetadata;
        }) =>
            (
                await http.patch<{ id: number; payload: AiMetadata }>(
                    update.url({ record: recordId, metadata: metadataId }),
                    payload,
                )
            ).data,
    });
}

/**
 * Make an earlier generation active again, then reload the metadata props.
 */
export function useActivateMetadata(recordId: string) {
    return useMutation({
        mutationFn: async (metadataId: number) =>
            (
                await http.post<{ id: number }>(
                    activate.url({ record: recordId, metadata: metadataId }),
                )
            ).data,
        onSuccess: () => {
            router.reload({ only: ['metadata', 'metadataHistory'] });
        },
    });
}
