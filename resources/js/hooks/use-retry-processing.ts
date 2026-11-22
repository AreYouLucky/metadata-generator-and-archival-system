import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { retry } from '@/routes/records';
import type { PipelineOptionValues, PipelineStageName } from '@/types/records';

/**
 * Run processing again: the whole pipeline after a failure or a cancel, or,
 * with `stage`, one failed or skipped step and the steps that read its
 * results. The workspace reloads its stages; progress then arrives over Reverb.
 */
export function useRetryProcessing(recordId: string) {
    return useMutation({
        mutationFn: async (input: {
            options: PipelineOptionValues;
            stage?: PipelineStageName;
        }) =>
            (
                await http.post<{
                    queued: boolean;
                    stages: PipelineStageName[];
                }>(retry.url(recordId), {
                    ...input.options,
                    custom_prompt: input.options.custom_prompt || null,
                    stage: input.stage ?? null,
                })
            ).data,
        onSuccess: () => router.reload({ only: ['record', 'stages'] }),
    });
}
