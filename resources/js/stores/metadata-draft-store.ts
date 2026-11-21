import { create } from 'zustand';
import type { AiMetadata, Chapter } from '@/types/records';

/**
 * The encoder's unsaved edits to the active generation, shared by the
 * inspector and the timeline (chapters can be added and dragged on either).
 * A new active generation replaces the draft, as switching generations did.
 */
type MetadataDraftState = {
    metadataId: number | null;
    draft: AiMetadata | null;
    dirty: boolean;
    load: (metadataId: number | null, payload: AiMetadata | null) => void;
    change: (changes: Partial<AiMetadata>) => void;
    setChapters: (chapters: Chapter[]) => void;
    /** Add a chapter at `start` (whole seconds); false if one is already there. */
    addChapter: (start: number) => boolean;
    /** Move one chapter, kept between its neighbours so the order never changes. */
    moveChapter: (index: number, start: number) => void;
    markSaved: () => void;
};

/** Chapters always play in order; the timeline relies on it. */
export function sortChapters(chapters: Chapter[]): Chapter[] {
    return [...chapters].sort((first, second) => first.start - second.start);
}

export const useMetadataDraftStore = create<MetadataDraftState>((set, get) => ({
    metadataId: null,
    draft: null,
    dirty: false,
    load: (metadataId, payload) =>
        set({ metadataId, draft: payload, dirty: false }),
    change: (changes) =>
        set((state) =>
            state.draft
                ? { draft: { ...state.draft, ...changes }, dirty: true }
                : state,
        ),
    setChapters: (chapters) =>
        set((state) =>
            state.draft
                ? {
                      draft: {
                          ...state.draft,
                          chapters: sortChapters(chapters),
                      },
                      dirty: true,
                  }
                : state,
        ),
    addChapter: (start) => {
        const { draft } = get();
        const second = Math.max(0, Math.round(start));

        if (
            !draft ||
            draft.chapters.some(
                (chapter) => Math.abs(chapter.start - second) < 1,
            )
        ) {
            return false;
        }

        get().setChapters([
            ...draft.chapters,
            { start: second, title: `Chapter ${draft.chapters.length + 1}` },
        ]);

        return true;
    },
    moveChapter: (index, start) =>
        set((state) => {
            const chapters = state.draft?.chapters;

            if (!state.draft || !chapters?.[index]) {
                return state;
            }

            const lowest = index > 0 ? chapters[index - 1].start + 1 : 0;
            const highest =
                index < chapters.length - 1
                    ? chapters[index + 1].start - 1
                    : Number.POSITIVE_INFINITY;
            const clamped =
                Math.round(Math.min(highest, Math.max(lowest, start)) * 10) /
                10;

            return {
                draft: {
                    ...state.draft,
                    chapters: chapters.map((chapter, position) =>
                        position === index
                            ? { ...chapter, start: clamped }
                            : chapter,
                    ),
                },
                dirty: true,
            };
        }),
    markSaved: () => set({ dirty: false }),
}));
