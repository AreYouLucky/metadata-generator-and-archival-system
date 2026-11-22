import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { cancel } from '@/routes/records';

/**
 * Stop a queued or running pipeline. Finished stages keep their results; the
 * page reloads the props given (the workspace's stages by default, the record
 * list's rows there), and "Retry" runs everything again.
 */
export function useCancelProcessing(
    recordId: string,
    reload: string[] = ['record', 'stages'],
) {
    return useMutation({
        mutationFn: async () =>
            (await http.post<{ cancelled: boolean }>(cancel.url(recordId)))
                .data,
        onSuccess: () => router.reload({ only: reload }),
    });
}
