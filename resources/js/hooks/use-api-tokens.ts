import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy, store, webhookSecret } from '@/routes/api-tokens';
import type { ApiToken, ApiTokenInput } from '@/types/api-tokens';

/** The settings page lists tokens as an Inertia prop; reload just those. */
const reloadTokens = () => router.reload({ only: ['tokens'] });

/** Create a token; its text comes back once, in `plain_text`. */
export function useCreateApiToken() {
    return useMutation({
        mutationFn: async (input: ApiTokenInput) =>
            (
                await http.post<{ token: ApiToken; plain_text: string }>(
                    store.url(),
                    input,
                )
            ).data,
        onSuccess: reloadTokens,
    });
}

export function useRevokeApiToken() {
    return useMutation({
        mutationFn: async (id: number) => {
            await http.delete(destroy.url(id));
        },
        onSuccess: reloadTokens,
    });
}

/** Make a new webhook signing secret (shown once). */
export function useRegenerateWebhookSecret() {
    return useMutation({
        mutationFn: async () =>
            (await http.post<{ secret: string }>(webhookSecret.url())).data
                .secret,
        onSuccess: () => router.reload({ only: ['hasWebhookSecret'] }),
    });
}
