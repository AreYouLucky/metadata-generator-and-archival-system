import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store as buildZip } from '@/routes/batches/zip';
import type { BatchProgressEvent, BatchZipStatus } from '@/types/batches';
import type { RecordProgressEvent } from '@/types/records';

/**
 * Build (or rebuild) the batch's ZIP of exports. The server answers at once;
 * the batch channel says when the ZIP is ready.
 */
export function useBuildBatchZip(batchId: string) {
    return useMutation({
        mutationFn: async (input: { thumbnails: boolean }) =>
            (
                await http.post<{ zip_status: BatchZipStatus }>(
                    buildZip.url(batchId),
                    input,
                )
            ).data,
    });
}

type Handlers = {
    onRecordProgress: (event: RecordProgressEvent) => void;
};

/**
 * Live updates on the private `batches.{id}` channel: every record's stage
 * progress (handled locally), and the batch's counts and ZIP (a partial
 * reload). Handlers must be stable (useCallback).
 */
export function useBatchChannel(
    batchId: string,
    { onRecordProgress }: Handlers,
) {
    const channel = `batches.${batchId}`;

    useEcho<RecordProgressEvent>(
        channel,
        '.record.progress',
        onRecordProgress,
        [batchId, onRecordProgress],
    );

    useEcho<BatchProgressEvent>(
        channel,
        '.batch.progress',
        () => router.reload({ only: ['batch', 'records'] }),
        [batchId],
    );
}
