import { isAxiosError } from 'axios';
import { http } from '@/lib/utilities';
import {
    chunk as chunkUrl,
    show as uploadStatus,
    store as startUpload,
} from '@/routes/records/uploads';

/** Under PHP's 40 MB post_max_size, big enough to keep request overhead low. */
const CHUNK_BYTES = 8 * 1024 * 1024;
const MAX_ATTEMPTS = 4;

type UploadSession = { id: string; received: number };

/**
 * Sends a file in 8 MB chunks (resumable: a rejected or failed chunk is
 * retried from the byte count the server reports). Returns the upload id,
 * which a later request claims (a new record, or an extra file of one).
 */
export async function uploadInChunks(
    file: File,
    onProgress: (fraction: number) => void,
): Promise<string> {
    const { data: session } = await http.post<UploadSession>(
        startUpload.url(),
        { filename: file.name, size: file.size },
    );

    let offset = 0;
    let attempts = 0;

    while (offset < file.size) {
        const body = new FormData();
        body.append('offset', String(offset));
        body.append('chunk', file.slice(offset, offset + CHUNK_BYTES), 'chunk');

        try {
            const { data } = await http.post<UploadSession>(
                chunkUrl.url(session.id),
                body,
            );
            offset = data.received;
            attempts = 0;
        } catch (error) {
            attempts++;

            if (attempts >= MAX_ATTEMPTS || !isRetryable(error)) {
                throw error;
            }

            const { data } = await http.get<UploadSession>(
                uploadStatus.url(session.id),
            );
            offset = data.received;
        }

        onProgress(offset / file.size);
    }

    return session.id;
}

function isRetryable(error: unknown): boolean {
    if (!isAxiosError(error)) {
        return false;
    }

    const status = error.response?.status;

    // No response (network), out-of-order chunk (409), rate limit or server error.
    return (
        status === undefined ||
        status === 409 ||
        status === 429 ||
        status >= 500
    );
}
