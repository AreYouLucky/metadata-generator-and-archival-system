import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { uploadInChunks } from '@/hooks/use-upload-record';
import { http } from '@/lib/utilities';
import { fixity, update } from '@/routes/records';
import { store as storeDescriptiveSuggestion } from '@/routes/records/descriptive-suggestions';
import {
    destroy as destroyAttachment,
    store as storeAttachment,
} from '@/routes/records/attachments';
import { store as storeFile } from '@/routes/records/files';
import {
    destroy as destroyKeyword,
    store as storeKeyword,
    update as updateKeyword,
} from '@/routes/records/keywords';
import { update as updateProvenance } from '@/routes/records/provenance';
import { update as updateRights } from '@/routes/records/rights';
import {
    destroy as destroySubject,
    store as storeSubject,
    update as updateSubject,
} from '@/routes/records/subjects';
import { store as storeWork } from '@/routes/works';
import type {
    ArchiveProvenance,
    ArchiveRights,
    RecordUpdate,
} from '@/types/archive';

/**
 * The Archive panel's data is the `archive` Inertia prop; after a change the
 * workspace reloads just that (and `record`, whose header shows the title,
 * accession number, classification and embargo).
 */
export const reloadArchive = (withRecord = false) =>
    router.reload({
        only: withRecord
            ? ['archive', 'record', 'archiveOptions']
            : ['archive'],
    });

/** Save some catalogue fields (one Archive sub-tab). */
export function useUpdateRecord(recordId: string) {
    return useMutation({
        mutationFn: async (fields: RecordUpdate) => {
            await http.patch(update.url(recordId), fields);
        },
        onSuccess: () => reloadArchive(true),
    });
}

export function useSubjectMutations(recordId: string) {
    return {
        add: useMutation({
            mutationFn: async (input: { subject: string; type: string }) => {
                await http.post(storeSubject.url(recordId), input);
            },
            onSuccess: () => reloadArchive(),
        }),
        update: useMutation({
            mutationFn: async ({
                id,
                ...input
            }: {
                id: number;
                subject?: string;
                type?: string;
                is_confirmed?: boolean;
            }) => {
                await http.patch(
                    updateSubject.url({ record: recordId, subject: id }),
                    input,
                );
            },
            onSuccess: () => reloadArchive(),
        }),
        remove: useMutation({
            mutationFn: async (id: number) => {
                await http.delete(
                    destroySubject.url({ record: recordId, subject: id }),
                );
            },
            onSuccess: () => reloadArchive(),
        }),
    };
}

export function useKeywordMutations(recordId: string) {
    return {
        add: useMutation({
            mutationFn: async (keyword: string) => {
                await http.post(storeKeyword.url(recordId), { keyword });
            },
            onSuccess: () => reloadArchive(),
        }),
        update: useMutation({
            mutationFn: async ({
                id,
                ...input
            }: {
                id: number;
                keyword?: string;
                is_confirmed?: boolean;
            }) => {
                await http.patch(
                    updateKeyword.url({ record: recordId, keyword: id }),
                    input,
                );
            },
            onSuccess: () => reloadArchive(),
        }),
        remove: useMutation({
            mutationFn: async (id: number) => {
                await http.delete(
                    destroyKeyword.url({ record: recordId, keyword: id }),
                );
            },
            onSuccess: () => reloadArchive(),
        }),
    };
}

export function useSaveRights(recordId: string) {
    return useMutation({
        mutationFn: async (rights: ArchiveRights) => {
            await http.put(updateRights.url(recordId), rights);
        },
        onSuccess: () => reloadArchive(true),
    });
}

export function useSaveProvenance(recordId: string) {
    return useMutation({
        mutationFn: async (provenance: ArchiveProvenance) => {
            await http.put(updateProvenance.url(recordId), provenance);
        },
        onSuccess: () => reloadArchive(),
    });
}

export function useAttachmentMutations(recordId: string) {
    return {
        upload: useMutation({
            mutationFn: async (input: {
                file: File;
                kind: string;
                description: string;
            }) => {
                const body = new FormData();
                body.append('file', input.file);
                body.append('kind', input.kind);
                body.append('description', input.description);
                await http.post(storeAttachment.url(recordId), body);
            },
            onSuccess: () => reloadArchive(),
        }),
        remove: useMutation({
            mutationFn: async (id: string) => {
                await http.delete(
                    destroyAttachment.url({ record: recordId, attachment: id }),
                );
            },
            onSuccess: () => reloadArchive(),
        }),
    };
}

/** Upload a mezzanine or access file in chunks, then add it to the record. */
export function useAddRecordFile(recordId: string) {
    return useMutation({
        mutationFn: async (input: {
            file: File;
            role: 'mezzanine' | 'access';
            expectedSha256: string;
            onProgress: (fraction: number) => void;
        }) => {
            const uploadId = await uploadInChunks(input.file, input.onProgress);
            await http.post(storeFile.url(recordId), {
                upload_id: uploadId,
                role: input.role,
                expected_sha256: input.expectedSha256 || null,
            });
        },
        onSuccess: () => reloadArchive(),
    });
}

/**
 * "Fill with AI" for Archive → Descriptive. Queued: the suggestions arrive
 * over Reverb (`record.descriptive-suggested`) and fill the form unsaved.
 */
export function useSuggestDescriptive(recordId: string) {
    return useMutation({
        mutationFn: async () =>
            (
                await http.post<{ queued: boolean }>(
                    storeDescriptiveSuggestion.url(recordId),
                )
            ).data,
    });
}

/** "Check now": re-hash the master and mezzanine files. */
export function useRunFixity(recordId: string) {
    return useMutation({
        mutationFn: async () =>
            (await http.post<{ queued: number }>(fixity.url(recordId))).data,
    });
}

export function useCreateWork() {
    return useMutation({
        mutationFn: async (input: {
            title: string;
            total_parts: number | null;
        }) =>
            (
                await http.post<{ work: { id: string; title: string } }>(
                    storeWork.url(),
                    input,
                )
            ).data.work,
    });
}
