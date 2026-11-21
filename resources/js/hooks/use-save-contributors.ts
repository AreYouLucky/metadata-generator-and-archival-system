import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { sync } from '@/routes/records/contributors';
import type { Contributor } from '@/types/records';

/**
 * Save the whole credits list in display order (rows without an id are new,
 * missing rows are removed). The saved list replaces the page's
 * `contributors` prop, so the credits panel and the frame-text tool agree.
 */
export function useSaveContributors(recordId: string) {
    return useMutation({
        mutationFn: async (contributors: Contributor[]) =>
            (
                await http.put<Required<Contributor>[]>(sync.url(recordId), {
                    contributors: contributors.map(({ id, role, name }) => ({
                        id: id ?? null,
                        role,
                        name,
                    })),
                })
            ).data,
        onSuccess: (saved) => router.replaceProp('contributors', saved),
    });
}
