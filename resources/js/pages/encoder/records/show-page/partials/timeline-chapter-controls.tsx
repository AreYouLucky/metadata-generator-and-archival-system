import { BookmarkPlus, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useSaveMetadataDraft } from '@/hooks/use-save-metadata-draft';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';

type Props = {
    recordId: string;
    editable: boolean;
    hasMetadata: boolean;
    onAdd: () => void;
};

/**
 * Timeline header controls: add a chapter at the playhead and, while the
 * metadata has unsaved changes (dragged chapters included), save them
 * without switching to the inspector.
 */
export default function TimelineChapterControls({
    recordId,
    editable,
    hasMetadata,
    onAdd,
}: Props) {
    const dirty = useMetadataDraftStore((state) => state.dirty);
    const { save, isPending } = useSaveMetadataDraft(recordId);

    return (
        <div className="flex items-center gap-1">
            <Button
                variant="ghost"
                size="sm"
                className="h-7 gap-1.5 px-2 text-xs text-foreground/80"
                disabled={!editable}
                onClick={onAdd}
                title={
                    hasMetadata
                        ? 'Add a chapter at the playhead (or double-click the chapters track)'
                        : 'Generate metadata first: chapters are part of it'
                }
            >
                <BookmarkPlus aria-hidden />
                Add chapter
            </Button>
            {dirty && (
                <Button
                    size="sm"
                    className="h-7 gap-1.5 px-2 text-xs"
                    disabled={isPending}
                    onClick={save}
                >
                    {isPending ? <Spinner /> : <Save aria-hidden />}
                    Save changes
                </Button>
            )}
        </div>
    );
}
