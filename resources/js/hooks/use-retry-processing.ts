import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { retry } from '@/routes/records';
import type { PresetName } from '@/types/records';

/**
 * Run the whole pipeline again for a record whose processing failed. The
 * workspace reloads its stages; progress then arrives over Reverb.
 */
export function useRetryProcessing(recordId: string) {
    return useMutation({
        mutationFn: async (input: {
            preset: PresetName;
            customPrompt: string;
        }) =>
            (
                await http.post<{ queued: boolean }>(retry.url(recordId), {
                    preset: input.preset,
                    custom_prompt: input.customPrompt || null,
                })
            ).data,
        onSuccess: () => router.reload({ only: ['record', 'stages'] }),
    });
}
