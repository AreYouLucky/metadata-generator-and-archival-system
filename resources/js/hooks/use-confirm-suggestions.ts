import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { confirm } from '@/routes/records/suggestions';

/**
 * "Confirm all": every pending AI subject and keyword suggestion of a record
 * in one request. `reloadProps` are the Inertia props to refresh afterwards
 * (the workspace's `archive`, or the review page's record and archive).
 */
export function useConfirmSuggestions(recordId: string, reloadProps: string[]) {
    return useMutation({
        mutationFn: async () =>
            (await http.post<{ confirmed: number }>(confirm.url(recordId)))
                .data,
        onSuccess: () => router.reload({ only: reloadProps }),
    });
}
