import { ChevronDown, Info, SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import VisualAnalysisOptions from '@/components/encoder/visual-analysis-options';
import type { VisualAnalysisValues } from '@/components/encoder/visual-analysis-options';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { PresetName, PresetRules } from '@/types/records';

export type UploadOptionValues = {
    title: string;
    preset: PresetName;
    customPrompt: string;
    expectedMd5: string;
    expectedSha256: string;
} & VisualAnalysisValues;

type Props = {
    /** The project picker, shown first. */
    children?: ReactNode;
    presets: PresetRules[];
    values: UploadOptionValues;
    onChange: (values: UploadOptionValues) => void;
    disabled: boolean;
};

export default function UploadOptions({
    children,
    presets,
    values,
    onChange,
    disabled,
}: Props) {
    const set = (changes: Partial<UploadOptionValues>) =>
        onChange({ ...values, ...changes });

    return (
        <EditorPanel
            title="Inspector · Import settings"
            icon={<SlidersHorizontal className="size-3.5" aria-hidden />}
        >
            <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-3">
                {children}
                <div className="grid gap-2">
                    <Label htmlFor="title">Working title</Label>
                    <Input
                        id="title"
                        value={values.title}
                        maxLength={250}
                        disabled={disabled}
                        placeholder="Defaults to the file name"
                        onChange={(event) => set({ title: event.target.value })}
                    />
                </div>

                <div className="grid gap-2">
                    <div className="grid gap-2">
                        <Label htmlFor="preset">Metadata for</Label>
                        <Select
                            value={values.preset}
                            disabled={disabled}
                            onValueChange={(preset) =>
                                set({ preset: preset as PresetName })
                            }
                        >
                            <SelectTrigger id="preset">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {presets.map((preset) => (
                                    <SelectItem
                                        key={preset.name}
                                        value={preset.name}
                                    >
                                        {preset.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="custom-prompt">
                            Instructions for the AI (optional)
                        </Label>
                        <Textarea
                            id="custom-prompt"
                            value={values.customPrompt}
                            maxLength={2000}
                            disabled={disabled}
                            rows={2}
                            placeholder="e.g. Mention the regional office. Write in Filipino."
                            onChange={(event) =>
                                set({ customPrompt: event.target.value })
                            }
                        />
                    </div>
                </div>

                <VisualAnalysisOptions
                    idPrefix="upload"
                    values={values}
                    onChange={(visual) => set(visual)}
                    disabled={disabled}
                />

                <Collapsible>
                    <CollapsibleTrigger className="group flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:underline focus-visible:outline-none">
                        <ChevronDown
                            className="size-4 transition-transform group-data-[state=open]:rotate-180"
                            aria-hidden
                        />
                        Expected checksums from a source manifest
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3 grid gap-4">
                        <p className="text-sm text-muted-foreground">
                            If the file came with checksums, paste them: a
                            mismatch after upload flags the record.
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="expected-sha256">SHA-256</Label>
                            <Input
                                id="expected-sha256"
                                value={values.expectedSha256}
                                disabled={disabled}
                                spellCheck={false}
                                className="font-mono text-xs"
                                onChange={(event) =>
                                    set({
                                        expectedSha256:
                                            event.target.value.trim(),
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="expected-md5">MD5</Label>
                            <Input
                                id="expected-md5"
                                value={values.expectedMd5}
                                disabled={disabled}
                                spellCheck={false}
                                className="font-mono text-xs"
                                onChange={(event) =>
                                    set({
                                        expectedMd5: event.target.value.trim(),
                                    })
                                }
                            />
                        </div>
                    </CollapsibleContent>
                </Collapsible>
                <p className="mt-auto flex gap-2 rounded-md bg-white/5 p-3 text-xs text-neutral-400">
                    <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                    Audio is transcribed on this organisation's server. The
                    transcript text (not the video) is then sent to OpenAI to
                    write titles, descriptions and tags
                    {values.visualAnalysis
                        ? ', with small copies of the key frames to describe what is shown.'
                        : '.'}
                </p>
            </div>
        </EditorPanel>
    );
}
