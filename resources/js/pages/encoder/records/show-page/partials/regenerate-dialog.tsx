import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PresetName, PresetRules } from '@/types/records';
import RegenerateForm from './regenerate-form';

type Props = {
    recordId: string;
    presets: PresetRules[];
    initialPreset: PresetName;
    initialPrompt: string;
    generating: boolean;
};

/**
 * "Regenerate" in the inspector header: the preset and instructions form in
 * a dialog, so it doesn't take room above the fields.
 */
export default function RegenerateDialog({
    recordId,
    presets,
    initialPreset,
    initialPrompt,
    generating,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={generating}
                    aria-label="Regenerate metadata"
                    title="Regenerate metadata"
                >
                    <RefreshCw
                        className={generating ? 'animate-spin' : undefined}
                        aria-hidden
                    />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Regenerate metadata</DialogTitle>
                    <DialogDescription>
                        Writes a new generation from the transcript. The current
                        one stays in the history.
                    </DialogDescription>
                </DialogHeader>
                <RegenerateForm
                    recordId={recordId}
                    presets={presets}
                    initialPreset={initialPreset}
                    initialPrompt={initialPrompt}
                    generating={generating}
                    hasMetadata
                    onStarted={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
