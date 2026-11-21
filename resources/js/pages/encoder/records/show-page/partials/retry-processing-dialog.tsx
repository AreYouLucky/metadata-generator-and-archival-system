import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useRetryProcessing } from '@/hooks/use-retry-processing';
import { getErrorMessage } from '@/lib/utilities';
import type { PresetName, PresetRules } from '@/types/records';

type Props = {
    recordId: string;
    presets: PresetRules[];
    initialPreset: PresetName;
};

/**
 * Re-runs every stage of a failed record. The upload's platform choice isn't
 * stored, so it is asked for again here.
 */
export default function RetryProcessingDialog({
    recordId,
    presets,
    initialPreset,
}: Props) {
    const [open, setOpen] = useState(false);
    const [preset, setPreset] = useState<PresetName>(initialPreset);
    const [prompt, setPrompt] = useState('');
    const retry = useRetryProcessing(recordId);

    const submit = () =>
        retry.mutate(
            { preset, customPrompt: prompt },
            {
                onSuccess: () => {
                    setOpen(false);
                    toast.success('Processing restarted.');
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'Processing could not restart.'),
                    ),
            },
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary" className="h-7">
                    <RotateCcw aria-hidden />
                    Retry
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retry processing</DialogTitle>
                    <DialogDescription>
                        Every stage runs again, from checksums to metadata.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="retry-preset">Platform</Label>
                        <Select
                            value={preset}
                            onValueChange={(value) =>
                                setPreset(value as PresetName)
                            }
                        >
                            <SelectTrigger id="retry-preset">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {presets.map((item) => (
                                    <SelectItem
                                        key={item.name}
                                        value={item.name}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="retry-prompt">
                            Instructions (optional)
                        </Label>
                        <Textarea
                            id="retry-prompt"
                            rows={2}
                            maxLength={2000}
                            value={prompt}
                            placeholder="e.g. More formal. Mention the province."
                            onChange={(event) => setPrompt(event.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button onClick={submit} disabled={retry.isPending}>
                        <RotateCcw aria-hidden />
                        Retry processing
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
