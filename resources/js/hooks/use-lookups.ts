import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import * as genres from '@/routes/admin/genres';
import * as videoTypes from '@/routes/admin/video-types';
import type { LookupItem, LookupKind } from '@/types/lookups';

export type LookupInput = { name: string; sort_order: number };

const ROUTES = { genres, 'video-types': videoTypes } as const;

/**
 * After any change the list page reloads its items (the Archive panel's
 * options are read fresh each time the workspace opens).
 */
const reloadItems = () => router.reload({ only: ['items'] });

export function useCreateLookup(kind: LookupKind) {
    return useMutation({
        mutationFn: async (input: LookupInput) =>
            (await http.post<LookupItem>(ROUTES[kind].store.url(), input)).data,
        onSuccess: reloadItems,
    });
}

export function useUpdateLookup(kind: LookupKind) {
    return useMutation({
        mutationFn: async ({ id, ...input }: LookupInput & { id: number }) =>
            (await http.patch<LookupItem>(ROUTES[kind].update.url(id), input))
                .data,
        onSuccess: reloadItems,
    });
}

export function useDeleteLookup(kind: LookupKind) {
    return useMutation({
        mutationFn: async (id: number) =>
            (
                await http.delete<{ deleted: boolean }>(
                    ROUTES[kind].destroy.url(id),
                )
            ).data,
        onSuccess: reloadItems,
    });
}
