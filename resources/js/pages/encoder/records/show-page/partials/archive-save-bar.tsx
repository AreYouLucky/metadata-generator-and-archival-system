import { Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    dirty: boolean;
    pending: boolean;
    onSave: () => void;
    disabled?: boolean;
};

/** The save button at the foot of an Archive sub-tab. */
export default function ArchiveSaveBar({
    dirty,
    pending,
    onSave,
    disabled,
}: Props) {
    return (
        <div className="sticky bottom-0 -mx-5 -mb-5 flex items-center justify-end gap-3 border-t border-border bg-card px-5 py-3">
            {dirty && (
                <span className="text-xs text-muted-foreground">
                    Unsaved changes
                </span>
            )}
            <Button
                size="sm"
                onClick={onSave}
                disabled={!dirty || pending || disabled}
            >
                {pending ? <Spinner /> : <Save aria-hidden />}
                Save
            </Button>
        </div>
    );
}
