import { router } from '@inertiajs/react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { options } from '@/routes/agencies';
import { destroy, store, update } from '@/routes/admin/agencies';
import type { AgencyOption, AgencySummary } from '@/types/agencies';

const OPTIONS_KEY = ['agencies', 'options'] as const;

/**
 * Agencies with their units for the pickers (a short, admin-managed list).
 */
export function useAgencyOptions() {
    return useQuery({
        queryKey: OPTIONS_KEY,
        queryFn: async () =>
            (await http.get<{ data: AgencyOption[] }>(options.url())).data.data,
        staleTime: 5 * 60_000,
    });
}

/** After any admin change: refresh the pickers and the Agencies page. */
function useAfterChange() {
    const queryClient = useQueryClient();

    return () => {
        void queryClient.invalidateQueries({ queryKey: OPTIONS_KEY });
        router.reload({ only: ['agencies'] });
    };
}

export type AgencyInput = {
    name: string;
    acronym: string;
    parent_id?: number | null;
};

export function useCreateAgency() {
    const onSuccess = useAfterChange();

    return useMutation({
        mutationFn: async (input: AgencyInput) =>
            (
                await http.post<AgencySummary>(store.url(), {
                    ...input,
                    acronym: input.acronym || null,
                })
            ).data,
        onSuccess,
    });
}

export function useUpdateAgency() {
    const onSuccess = useAfterChange();

    return useMutation({
        mutationFn: async ({ id, ...input }: AgencyInput & { id: number }) =>
            (
                await http.patch<AgencySummary>(update.url(id), {
                    name: input.name,
                    acronym: input.acronym || null,
                })
            ).data,
        onSuccess,
    });
}

export function useDeleteAgency() {
    const onSuccess = useAfterChange();

    return useMutation({
        mutationFn: async (id: number) =>
            (await http.delete<{ deleted: boolean }>(destroy.url(id))).data,
        onSuccess,
    });
}
