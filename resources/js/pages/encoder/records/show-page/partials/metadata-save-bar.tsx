import { Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useSaveMetadataDraft } from '@/hooks/use-save-metadata-draft';

type Props = {
    recordId: string;
    dirty: boolean;
    generating: boolean;
};

/**
 * The save strip under the Metadata and Chapters tabs. Both edit the same
 * draft, so saving either saves both.
 */
export default function MetadataSaveBar({
    recordId,
    dirty,
    generating,
}: Props) {
    const { save, isPending } = useSaveMetadataDraft(recordId);

    return (
        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-white/10 px-5 py-3">
            {dirty && (
                <span className="text-xs text-neutral-400">
                    Unsaved changes
                </span>
            )}
            <Button
                size="sm"
                onClick={save}
                disabled={!dirty || isPending || generating}
            >
                {isPending ? <Spinner /> : <Save aria-hidden />}
                Save changes
            </Button>
        </div>
    );
}
