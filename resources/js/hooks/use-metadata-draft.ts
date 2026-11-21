import { useEffect } from 'react';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import type { ActiveMetadata, AiMetadata, Chapter } from '@/types/records';

/**
 * The editable copy of the active generation. Until the store has loaded this
 * generation (first render, or right after switching), the saved payload is
 * shown, so the inspector never flashes empty.
 */
export function useMetadataDraft(metadata: ActiveMetadata | null): {
    draft: AiMetadata | null;
    dirty: boolean;
} {
    const metadataId = useMetadataDraftStore((state) => state.metadataId);
    const draft = useMetadataDraftStore((state) => state.draft);
    const dirty = useMetadataDraftStore((state) => state.dirty);
    const current = (metadata?.id ?? null) === metadataId;

    return {
        draft: current ? draft : (metadata?.payload ?? null),
        dirty: current && dirty,
    };
}

/**
 * Just the draft's chapters, for the timeline: it re-renders when chapters
 * change, not on every keystroke in the description.
 */
export function useDraftChapters(metadata: ActiveMetadata | null): Chapter[] {
    const current = useMetadataDraftStore(
        (state) => state.metadataId === (metadata?.id ?? null),
    );
    const chapters = useMetadataDraftStore((state) => state.draft?.chapters);

    return (current ? chapters : metadata?.payload.chapters) ?? [];
}

/**
 * Loads the active generation into the shared draft whenever it changes, and
 * clears it when the workspace closes.
 */
export function useLoadMetadataDraft(metadata: ActiveMetadata | null) {
    const load = useMetadataDraftStore((state) => state.load);
    const metadataId = metadata?.id ?? null;
    const payload = metadata?.payload ?? null;

    // Only a different generation resets the draft (saving keeps the same id),
    // so the payload is deliberately not a dependency.
    useEffect(() => load(metadataId, payload), [metadataId, load]);
    useEffect(() => () => load(null, null), [load]);
}
