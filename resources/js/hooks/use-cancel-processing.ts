import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { cancel } from '@/routes/records';

/**
 * Stop a queued or running pipeline. Finished stages keep their results; the
 * workspace reloads its stages, and "Retry" runs everything again.
 */
export function useCancelProcessing(recordId: string) {
    return useMutation({
        mutationFn: async () =>
            (await http.post<{ cancelled: boolean }>(cancel.url(recordId)))
                .data,
        onSuccess: () => router.reload({ only: ['record', 'stages'] }),
    });
}
