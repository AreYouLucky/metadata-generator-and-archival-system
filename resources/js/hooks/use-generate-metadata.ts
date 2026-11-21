import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store } from '@/routes/records/metadata';
import type { PresetName } from '@/types/records';

/**
 * Queue a metadata regeneration. The result arrives over Reverb (the
 * `generate_metadata` stage completes and the workspace reloads its props).
 */
export function useGenerateMetadata(recordId: string) {
    return useMutation({
        mutationFn: async (input: {
            preset: PresetName;
            customPrompt: string;
        }) =>
            (
                await http.post<{ queued: boolean }>(store.url(recordId), {
                    preset: input.preset,
                    custom_prompt: input.customPrompt || null,
                })
            ).data,
    });
}
