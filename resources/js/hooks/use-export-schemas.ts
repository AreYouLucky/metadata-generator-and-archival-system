import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy, store, update } from '@/routes/export-schemas';
import type { ExportSchema, ExportSchemaInput } from '@/types/export-schemas';

/** The settings page lists schemas as an Inertia prop; reload just that. */
const reloadSchemas = () => router.reload({ only: ['schemas'] });

export function useCreateExportSchema() {
    return useMutation({
        mutationFn: async (input: ExportSchemaInput) =>
            (await http.post<{ schema: ExportSchema }>(store.url(), input)).data
                .schema,
        onSuccess: reloadSchemas,
    });
}

export function useUpdateExportSchema() {
    return useMutation({
        mutationFn: async ({
            id,
            ...input
        }: ExportSchemaInput & { id: number }) =>
            (await http.patch<{ schema: ExportSchema }>(update.url(id), input))
                .data.schema,
        onSuccess: reloadSchemas,
    });
}

export function useDeleteExportSchema() {
    return useMutation({
        mutationFn: async (id: number) => {
            await http.delete(destroy.url(id));
        },
        onSuccess: reloadSchemas,
    });
}
