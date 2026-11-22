import { RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import SpeakerOption from '@/components/encoder/speaker-option';
import VisualAnalysisOptions from '@/components/encoder/visual-analysis-options';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import type {
    PipelineOptionValues,
    PresetName,
    PresetRules,
    StageState,
} from '@/types/records';

type Props = {
    recordId: string;
    presets: PresetRules[];
    /** The choices made on upload; the form starts from them. */
    initialOptions: PipelineOptionValues;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** One step to run again (with those reading its results); null runs every step. */
    stage: StageState | null;
};

/**
 * Runs processing again: every stage of a failed or cancelled record, or one
 * failed or skipped step and the steps that use its results. The upload's
 * choices are filled in and can be changed (e.g. switch on speakers).
 */
export default function RetryProcessingDialog({
    recordId,
    presets,
    initialOptions,
    open,
    onOpenChange,
    stage,
}: Props) {
    const [options, setOptions] =
        useState<PipelineOptionValues>(initialOptions);
    const retry = useRetryProcessing(recordId);
    const set = (changes: Partial<PipelineOptionValues>) =>
        setOptions((current) => ({ ...current, ...changes }));

    // Start from the record's choices each time the dialog opens.
    useEffect(() => {
        if (open) {
            setOptions(initialOptions);
        }
    }, [open, initialOptions]);

    const submit = () =>
        retry.mutate(
            { options, stage: stage?.stage },
            {
                onSuccess: () => {
                    onOpenChange(false);
                    toast.success(
                        stage
                            ? `${stage.label} queued again.`
                            : 'Processing restarted.',
                    );
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'Processing could not restart.'),
                    ),
            },
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {stage ? `Retry ${stage.label}` : 'Retry processing'}
                    </DialogTitle>
                    <DialogDescription>
                        {stage
                            ? 'This step runs again, with the steps that use its results. Finished steps keep theirs.'
                            : 'Every stage runs again, from checksums to metadata.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="retry-preset">Platform</Label>
                        <Select
                            value={options.preset}
                            onValueChange={(value) =>
                                set({ preset: value as PresetName })
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
                            value={options.custom_prompt ?? ''}
                            placeholder="e.g. More formal. Mention the province."
                            onChange={(event) =>
                                set({ custom_prompt: event.target.value })
                            }
                        />
                    </div>
                    <SpeakerOption
                        idPrefix="retry"
                        checked={options.diarization}
                        onChange={(diarization) => set({ diarization })}
                    />
                    <VisualAnalysisOptions
                        idPrefix="retry"
                        values={{
                            visualAnalysis: options.visual_analysis,
                            ocr: options.ocr,
                            objectDetection: options.object_detection,
                        }}
                        onChange={(visual) =>
                            set({
                                visual_analysis: visual.visualAnalysis,
                                ocr: visual.ocr,
                                object_detection: visual.objectDetection,
                            })
                        }
                    />
                </div>
                <DialogFooter>
                    <Button onClick={submit} disabled={retry.isPending}>
                        <RotateCcw aria-hidden />
                        {stage ? 'Retry this step' : 'Retry processing'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
