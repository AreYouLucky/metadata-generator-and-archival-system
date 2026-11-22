import { useMutation } from '@tanstack/react-query';
import { uploadInChunks } from '@/hooks/use-upload-record';
import { http } from '@/lib/utilities';
import { store as storeBatch } from '@/routes/batches';
import { store as storeRecord } from '@/routes/records';
import type { PipelineOptionValues } from '@/types/records';

/** One clip in the media bin: a file from this computer or a web address. */
export type ImportItem =
    | { kind: 'file'; file: File; title: string }
    | { kind: 'url'; url: string; title: string };

/** An item as the media bin keeps it, with a local id for selection and keys. */
export type BinItem = ImportItem & { id: string };

export type ImportMediaInput = {
    items: ImportItem[];
    /** Name of the batch (several items only). */
    batchName: string;
    projectId: string;
    seriesId: string | null;
    options: PipelineOptionValues;
    /** A source manifest's checksums (a single uploaded file only). */
    expectedMd5: string;
    expectedSha256: string;
    /** Bytes sent so far across every file, and which item is uploading. */
    onProgress: (sentBytes: number, itemIndex: number) => void;
};

/**
 * Imports what is in the media bin. One item becomes one record
 * (POST /records); several become a batch (POST /batches), each its own
 * record. Files are uploaded first, one after another, in resumable chunks;
 * web addresses are downloaded by the server. Returns the page to open: the
 * record's workspace, or the batch page.
 */
export function useImportMedia() {
    return useMutation({
        mutationFn: async (input: ImportMediaInput) => {
            const shared = {
                project_id: input.projectId,
                project_series_id: input.seriesId,
                ...input.options,
                custom_prompt: input.options.custom_prompt || null,
            };
            let sentBefore = 0;

            const source = async (item: ImportItem, index: number) => {
                if (item.kind === 'url') {
                    return { url: item.url, title: item.title || null };
                }

                const uploadId = await uploadInChunks(item.file, (fraction) =>
                    input.onProgress(
                        sentBefore + fraction * item.file.size,
                        index,
                    ),
                );
                sentBefore += item.file.size;

                return { upload_id: uploadId, title: item.title || null };
            };

            if (input.items.length === 1) {
                const item = input.items[0];
                const { data } = await http.post<{ id: string; url: string }>(
                    storeRecord.url(),
                    {
                        ...shared,
                        ...(await source(item, 0)),
                        expected_md5:
                            item.kind === 'file'
                                ? input.expectedMd5 || null
                                : null,
                        expected_sha256:
                            item.kind === 'file'
                                ? input.expectedSha256 || null
                                : null,
                    },
                );

                return data;
            }

            const items = [];

            for (const [index, item] of input.items.entries()) {
                items.push(await source(item, index));
            }

            const { data } = await http.post<{ id: string; url: string }>(
                storeBatch.url(),
                { ...shared, name: input.batchName || null, items },
            );

            return data;
        },
    });
}
