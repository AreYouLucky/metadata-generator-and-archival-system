import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import type {
    PipelineStageName,
    RecordProgressEvent,
    RecordStageCompletedEvent,
} from '@/types/records';

/** Workspace props each finished stage changes (Inertia partial reload). */
const RELOAD_AFTER: Record<PipelineStageName, string[]> = {
    checksum: [],
    probe: ['media'],
    extract_audio: ['media'],
    transcribe: ['transcript'],
    generate_metadata: ['metadata', 'metadataHistory'],
};

/**
 * Live pipeline updates on the private `records.{id}` channel (Reverb).
 * Progress ticks update local state; a completed stage reloads only the
 * props it changed.
 */
export function useRecordChannel(
    recordId: string,
    onProgress: (event: RecordProgressEvent) => void,
) {
    useEcho<RecordProgressEvent>(
        `records.${recordId}`,
        '.record.progress',
        onProgress,
        [recordId, onProgress],
    );

    useEcho<RecordStageCompletedEvent>(
        `records.${recordId}`,
        '.record.stage-completed',
        (event) => {
            router.reload({
                only: ['record', 'stages', ...RELOAD_AFTER[event.stage]],
            });
        },
        [recordId],
    );
}
