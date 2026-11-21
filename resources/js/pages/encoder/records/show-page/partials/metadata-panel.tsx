import { Save, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useUpdateMetadata } from '@/hooks/use-update-metadata';
import { getErrorMessage } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type {
    ActiveMetadata,
    AiMetadata,
    MetadataHistoryItem,
    PresetRules,
} from '@/types/records';
import ChaptersList from './chapters-list';
import ChipInput from './chip-input';
import EditorPanel from '@/components/encoder/editor-panel';
import MetadataHistory from './metadata-history';
import RegenerateForm from './regenerate-form';
import TitleOptions from './title-options';

type Props = {
    recordId: string;
    metadata: ActiveMetadata | null;
    history: MetadataHistoryItem[];
    presets: PresetRules[];
    canEdit: boolean;
    hasTranscript: boolean;
    generating: boolean;
};

/**
 * Edit the active generation (keyed by its id, so a new generation resets the
 * form), regenerate with another preset or prompt, or go back to an older one.
 */
export default function MetadataPanel({
    recordId,
    metadata,
    history,
    presets,
    canEdit,
    hasTranscript,
    generating,
}: Props) {
    const [draft, setDraft] = useState<AiMetadata | null>(
        metadata?.payload ?? null,
    );
    const [dirty, setDirty] = useState(false);
    const update = useUpdateMetadata(recordId);
    const preset =
        presets.find((item) => item.name === metadata?.preset) ?? presets[0];
    const readOnly = !canEdit || generating;

    const change = (changes: Partial<AiMetadata>) => {
        setDraft((current) => (current ? { ...current, ...changes } : current));
        setDirty(true);
    };

    const save = () => {
        if (!metadata || !draft) {
            return;
        }

        update.mutate(
            { metadataId: metadata.id, payload: draft },
            {
                onSuccess: () => {
                    setDirty(false);
                    toast.success('Metadata saved.');
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(
                            error,
                            'The metadata could not be saved.',
                        ),
                    ),
            },
        );
    };

    const descriptionLength = draft?.description?.length ?? 0;

    return (
        <EditorPanel
            title={
                metadata
                    ? `Inspector · ${preset?.label ?? metadata.preset}`
                    : 'Inspector'
            }
            icon={<Sparkles className="size-3.5 text-brand" aria-hidden />}
            actions={
                history.length > 1 && (
                    <MetadataHistory
                        recordId={recordId}
                        history={history}
                        disabled={readOnly || dirty}
                    />
                )
            }
        >
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-3">
                {canEdit && hasTranscript && (
                    <RegenerateForm
                        recordId={recordId}
                        presets={presets}
                        initialPreset={
                            metadata?.preset ?? presets[0]?.name ?? 'generic'
                        }
                        initialPrompt={metadata?.custom_prompt ?? ''}
                        generating={generating}
                        hasMetadata={metadata !== null}
                    />
                )}

                {generating && (
                    <p
                        className="flex items-center gap-2 rounded-lg bg-brand-muted/60 p-3 text-sm"
                        aria-live="polite"
                    >
                        <Spinner />
                        Writing metadata… it appears here when ready.
                    </p>
                )}

                {!metadata || !draft ? (
                    !generating && (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            {hasTranscript
                                ? 'No metadata yet. Generate it from the transcript.'
                                : 'Metadata is generated once the transcript is ready.'}
                        </p>
                    )
                ) : (
                    <div
                        className={cn(
                            'grid gap-5',
                            generating && 'pointer-events-none opacity-60',
                        )}
                    >
                        <TitleOptions
                            titles={draft.titles}
                            selected={draft.selectedTitle}
                            maxLength={preset?.title_max ?? null}
                            disabled={readOnly}
                            onChange={(titles, selectedTitle) =>
                                change({ titles, selectedTitle })
                            }
                        />

                        <div className="grid gap-2">
                            <div className="flex items-baseline justify-between">
                                <Label htmlFor="ai-description">
                                    Description
                                </Label>
                                {preset && (
                                    <span
                                        className={cn(
                                            'text-xs tabular-nums',
                                            descriptionLength >
                                                preset.description_max
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {descriptionLength} /{' '}
                                        {preset.description_max}
                                    </span>
                                )}
                            </div>
                            <Textarea
                                id="ai-description"
                                rows={6}
                                value={draft.description ?? ''}
                                disabled={readOnly}
                                onChange={(event) =>
                                    change({ description: event.target.value })
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai-summary">Summary</Label>
                            <Textarea
                                id="ai-summary"
                                rows={3}
                                value={draft.summary ?? ''}
                                disabled={readOnly}
                                onChange={(event) =>
                                    change({ summary: event.target.value })
                                }
                            />
                        </div>

                        <ChipInput
                            id="ai-tags"
                            label="Tags"
                            values={draft.tags}
                            max={preset?.tags_max_count ?? 50}
                            disabled={readOnly}
                            onChange={(tags) => change({ tags })}
                        />
                        <ChipInput
                            id="ai-keywords"
                            label="Keywords"
                            values={draft.keywords}
                            max={50}
                            disabled={readOnly}
                            onChange={(keywords) => change({ keywords })}
                        />
                        {(preset?.hashtags_max ?? 0) > 0 && (
                            <ChipInput
                                id="ai-hashtags"
                                label="Hashtags"
                                values={draft.hashtags}
                                max={preset?.hashtags_max ?? 30}
                                prefix="#"
                                disabled={readOnly}
                                onChange={(hashtags) => change({ hashtags })}
                            />
                        )}

                        <ChaptersList
                            chapters={draft.chapters}
                            disabled={readOnly}
                            onChange={(chapters) => change({ chapters })}
                        />

                        {metadata && (
                            <p className="text-xs text-neutral-500">
                                Generated with {metadata.model}
                            </p>
                        )}
                    </div>
                )}
            </div>

            {canEdit && metadata && draft && (
                <div className="flex shrink-0 items-center justify-end gap-3 border-t border-white/10 p-2">
                    {dirty && (
                        <span className="text-xs text-neutral-400">
                            Unsaved changes
                        </span>
                    )}
                    <Button
                        size="sm"
                        onClick={save}
                        disabled={!dirty || update.isPending || generating}
                    >
                        {update.isPending ? <Spinner /> : <Save aria-hidden />}
                        Save changes
                    </Button>
                </div>
            )}
        </EditorPanel>
    );
}
