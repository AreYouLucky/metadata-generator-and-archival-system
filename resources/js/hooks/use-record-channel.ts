import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import type {
    PipelineStageName,
    RecordArchiveUpdatedEvent,
    RecordAudioExportUpdatedEvent,
    RecordProgressEvent,
    RecordStageCompletedEvent,
    RecordTitlesGeneratedEvent,
    RecordTranslationUpdatedEvent,
} from '@/types/records';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { RecordDescriptiveSuggestedEvent } from '@/types/archive';
import type { RecordClipUpdatedEvent } from '@/types/clips';
import type { RecordPublicationUpdatedEvent } from '@/types/publishing';
import { toast } from 'sonner';

/** Workspace props each finished stage changes (Inertia partial reload). */
const RELOAD_AFTER: Record<PipelineStageName, string[]> = {
    import: ['media', 'archive'],
    checksum: [],
    probe: ['media'],
    extract_audio: ['media'],
    transcribe: ['transcript', 'translations'],
    diarize: ['transcript', 'speakers'],
    scenes: ['visuals', 'archive'],
    frames: ['visuals'],
    vision: ['visuals'],
    ocr: ['visuals'],
    objects: ['visuals'],
    generate_metadata: ['metadata', 'metadataHistory', 'archive'],
    proxy: ['media', 'archive'],
};

type Handlers = {
    onProgress: (event: RecordProgressEvent) => void;
    onTranslation: (event: RecordTranslationUpdatedEvent) => void;
    onTitles: (event: RecordTitlesGeneratedEvent) => void;
};

/**
 * Live updates on the private `records.{id}` channel (Reverb). Progress ticks
 * update local state; a completed stage reloads only the props it changed;
 * translations and "Generate more" titles report through their own events.
 * Handlers must be stable (useCallback), as each change re-subscribes.
 */
export function useRecordChannel(
    recordId: string,
    { onProgress, onTranslation, onTitles }: Handlers,
) {
    const channel = `records.${recordId}`;

    useEcho<RecordProgressEvent>(channel, '.record.progress', onProgress, [
        recordId,
        onProgress,
    ]);

    useEcho<RecordStageCompletedEvent>(
        channel,
        '.record.stage-completed',
        (event) => {
            router.reload({
                only: ['record', 'stages', ...RELOAD_AFTER[event.stage]],
            });
        },
        [recordId],
    );

    useEcho<RecordTranslationUpdatedEvent>(
        channel,
        '.record.translation-updated',
        onTranslation,
        [recordId, onTranslation],
    );

    // A file check or fixity check finished in the background.
    useEcho<RecordArchiveUpdatedEvent>(
        channel,
        '.record.archive-updated',
        () => router.reload({ only: ['archive'] }),
        [recordId],
    );

    // "Fill with AI" for Archive → Descriptive.
    useEcho<RecordDescriptiveSuggestedEvent>(
        channel,
        '.record.descriptive-suggested',
        (event) => {
            const store = useWorkspaceStore.getState();

            if (event.error) {
                store.setDescriptivePending(false);
                toast.error(event.error);

                return;
            }

            store.setDescriptiveSuggestion(event.fields);
            store.setInspectorTab('archive');
        },
        [recordId],
    );

    // An audio export (MP3 or WAV) finished or failed.
    useEcho<RecordAudioExportUpdatedEvent>(
        channel,
        '.record.audio-export-updated',
        (event) => {
            router.reload({ only: ['audioExports'] });
            const name = event.format.toUpperCase();

            if (event.status === 'ready') {
                toast.success(`The ${name} audio is ready to download.`);
            } else {
                toast.error(
                    event.error ?? `The ${name} audio could not be made.`,
                );
            }
        },
        [recordId],
    );

    // A clip started, was cut, or failed.
    useEcho<RecordClipUpdatedEvent>(
        channel,
        '.record.clip-updated',
        (event) => {
            router.reload({ only: ['clips'] });

            if (event.status === 'done') {
                toast.success(`${event.title} is ready to download.`);
            } else if (event.status === 'failed') {
                toast.error(event.error ?? `${event.title} could not be cut.`);
            }
        },
        [recordId],
    );

    // A YouTube publication was queued, uploaded further, finished or failed.
    useEcho<RecordPublicationUpdatedEvent>(
        channel,
        '.record.publication-updated',
        (event) => {
            router.reload({ only: ['publications'] });

            if (event.status === 'published') {
                toast.success('The video is published on YouTube.');
            } else if (event.status === 'failed') {
                toast.error(event.error ?? 'Publishing to YouTube failed.');
            }
        },
        [recordId],
    );

    useEcho<RecordTitlesGeneratedEvent>(
        channel,
        '.record.titles-generated',
        onTitles,
        [recordId, onTitles],
    );
}
