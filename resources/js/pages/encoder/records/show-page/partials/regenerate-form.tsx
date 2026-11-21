import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useGenerateMetadata } from '@/hooks/use-generate-metadata';
import { getErrorMessage } from '@/lib/utilities';
import type { PresetName, PresetRules } from '@/types/records';

type Props = {
    recordId: string;
    presets: PresetRules[];
    initialPreset: PresetName;
    initialPrompt: string;
    generating: boolean;
    hasMetadata: boolean;
};

export default function RegenerateForm({
    recordId,
    presets,
    initialPreset,
    initialPrompt,
    generating,
    hasMetadata,
}: Props) {
    const [preset, setPreset] = useState<PresetName>(initialPreset);
    const [prompt, setPrompt] = useState(initialPrompt);
    const generate = useGenerateMetadata(recordId);

    const submit = () =>
        generate.mutate(
            { preset, customPrompt: prompt },
            {
                onSuccess: () => toast.success('Generating new metadata…'),
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'Metadata generation could not start.',
                        ),
                    ),
            },
        );

    return (
        <div className="grid gap-3 rounded-lg bg-muted/40 p-3">
            <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
                <div className="grid gap-1.5">
                    <Label htmlFor="regenerate-preset" className="text-xs">
                        Platform
                    </Label>
                    <Select
                        value={preset}
                        onValueChange={(value) =>
                            setPreset(value as PresetName)
                        }
                        disabled={generating}
                    >
                        <SelectTrigger
                            id="regenerate-preset"
                            className="bg-background"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {presets.map((item) => (
                                <SelectItem key={item.name} value={item.name}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="regenerate-prompt" className="text-xs">
                        Instructions (optional)
                    </Label>
                    <Textarea
                        id="regenerate-prompt"
                        rows={1}
                        maxLength={2000}
                        value={prompt}
                        disabled={generating}
                        className="min-h-9 bg-background"
                        placeholder="e.g. More formal. Mention the province."
                        onChange={(event) => setPrompt(event.target.value)}
                    />
                </div>
            </div>
            <div className="flex justify-end">
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={submit}
                    disabled={generating || generate.isPending}
                >
                    <RefreshCw
                        className={generating ? 'animate-spin' : undefined}
                        aria-hidden
                    />
                    {hasMetadata ? 'Regenerate' : 'Generate'}
                </Button>
            </div>
        </div>
    );
}
