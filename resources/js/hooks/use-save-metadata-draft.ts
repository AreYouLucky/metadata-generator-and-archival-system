import { toast } from 'sonner';
import { useUpdateMetadata } from '@/hooks/use-update-metadata';
import { getErrorMessage } from '@/lib/utilities';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';

/**
 * Save the shared metadata draft (inspector fields and timeline chapters
 * alike) to the active generation.
 */
export function useSaveMetadataDraft(recordId: string) {
    const update = useUpdateMetadata(recordId);

    const save = () => {
        const { metadataId, draft, markSaved } =
            useMetadataDraftStore.getState();

        if (metadataId === null || !draft) {
            return;
        }

        update.mutate(
            { metadataId, payload: draft },
            {
                onSuccess: () => {
                    // Edits made while the request ran stay unsaved.
                    if (useMetadataDraftStore.getState().draft === draft) {
                        markSaved();
                    }

                    toast.success('Metadata saved.');
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'The metadata could not be saved.',
                        ),
                    ),
            },
        );
    };

    return { save, isPending: update.isPending };
}
