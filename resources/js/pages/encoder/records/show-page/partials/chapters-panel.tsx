import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import { useMetadataDraft } from '@/hooks/use-metadata-draft';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import type { ActiveMetadata } from '@/types/records';
import ChaptersList from './chapters-list';
import MetadataSaveBar from './metadata-save-bar';

type Props = {
    recordId: string;
    /** The inspector tabs, shown in the header's top corner. */
    heading: ReactNode;
    metadata: ActiveMetadata | null;
    canEdit: boolean;
    generating: boolean;
};

/**
 * The chapters of the active generation, on their own tab. They are the same
 * draft the timeline edits, so a chapter dragged there moves here too.
 */
export default function ChaptersPanel({
    recordId,
    heading,
    metadata,
    canEdit,
    generating,
}: Props) {
    const { draft, dirty } = useMetadataDraft(metadata);
    const setChapters = useMetadataDraftStore((state) => state.setChapters);

    return (
        <EditorPanel title="Chapters" heading={heading}>
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5">
                {!metadata || !draft ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Chapters are part of the generated metadata. Generate it
                        first, then add or adjust chapters here or on the
                        timeline.
                    </p>
                ) : (
                    <>
                        <ChaptersList
                            chapters={draft.chapters}
                            disabled={!canEdit || generating}
                            onChange={setChapters}
                        />
                        {canEdit && (
                            <p className="text-xs text-neutral-500">
                                Tip: double-click the chapters track on the
                                timeline to add one there, and drag a chapter to
                                move it.
                            </p>
                        )}
                    </>
                )}
            </div>

            {canEdit && metadata && draft && (
                <MetadataSaveBar
                    recordId={recordId}
                    dirty={dirty}
                    generating={generating}
                />
            )}
        </EditorPanel>
    );
}
