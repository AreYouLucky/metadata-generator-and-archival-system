import { ChevronDown, SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import SpeakerOption from '@/components/encoder/speaker-option';
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
import type { AiProviders, PresetName, PresetRules } from '@/types/records';
import PrivacyNotice from './privacy-notice';

export type UploadOptionValues = {
    preset: PresetName;
    customPrompt: string;
    expectedMd5: string;
    expectedSha256: string;
    diarization: boolean;
} & VisualAnalysisValues;

type Props = {
    /** The project picker, shown first. */
    children?: ReactNode;
    presets: PresetRules[];
    values: UploadOptionValues;
    onChange: (values: UploadOptionValues) => void;
    /** The chosen clip's working title (null when no clip is chosen). */
    title: string | null;
    onTitleChange: (title: string) => void;
    /** Several clips: they share these settings and get a batch name. */
    batchName: string | null;
    onBatchNameChange: (name: string) => void;
    /** A manifest's checksums apply to a single uploaded file only. */
    checksumsAvailable: boolean;
    /** Some clips are web addresses (downloaded by the server). */
    hasUrls: boolean;
    /** Which providers hear the audio (deferred; undefined while loading). */
    providers: AiProviders | undefined;
    disabled: boolean;
};

export default function UploadOptions({
    children,
    presets,
    values,
    onChange,
    title,
    onTitleChange,
    batchName,
    onBatchNameChange,
    checksumsAvailable,
    hasUrls,
    providers,
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
                {batchName !== null && (
                    <div className="grid gap-2">
                        <Label htmlFor="batch-name">Batch name</Label>
                        <Input
                            id="batch-name"
                            value={batchName}
                            maxLength={120}
                            disabled={disabled}
                            placeholder="Defaults to the upload date"
                            onChange={(event) =>
                                onBatchNameChange(event.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Every clip becomes its own record, filed and
                            processed with the settings below.
                        </p>
                    </div>
                )}
                {title !== null && (
                    <div className="grid gap-2">
                        <Label htmlFor="title">
                            {batchName !== null
                                ? 'Working title of the chosen clip'
                                : 'Working title'}
                        </Label>
                        <Input
                            id="title"
                            value={title}
                            maxLength={250}
                            disabled={disabled}
                            placeholder="Defaults to the file name or the site's title"
                            onChange={(event) =>
                                onTitleChange(event.target.value)
                            }
                        />
                    </div>
                )}

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

                <SpeakerOption
                    idPrefix="upload"
                    checked={values.diarization}
                    onChange={(diarization) => set({ diarization })}
                    disabled={disabled}
                />

                <VisualAnalysisOptions
                    idPrefix="upload"
                    values={values}
                    onChange={(visual) => set(visual)}
                    disabled={disabled}
                />

                {checksumsAvailable && (
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
                                            expectedMd5:
                                                event.target.value.trim(),
                                        })
                                    }
                                />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                )}
                <PrivacyNotice
                    providers={providers}
                    hasUrls={hasUrls}
                    diarization={values.diarization}
                    visualAnalysis={values.visualAnalysis}
                />
            </div>
        </EditorPanel>
    );
}
