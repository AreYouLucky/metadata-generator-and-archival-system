import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useMetadataDraft } from '@/hooks/use-metadata-draft';
import { cn } from '@/lib/utils';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import type {
    ActiveMetadata,
    MetadataHistoryItem,
    PresetRules,
} from '@/types/records';
import ChipInput from './chip-input';
import GenerateTitlesButton from './generate-titles-button';
import MetadataHistory from './metadata-history';
import MetadataSaveBar from './metadata-save-bar';
import QuotesList from './quotes-list';
import RegenerateDialog from './regenerate-dialog';
import RegenerateForm from './regenerate-form';
import TitleOptions from './title-options';
import ToneBadges from './tone-badges';

type Props = {
    recordId: string;
    /** The inspector tabs, shown in the header's top corner. */
    heading: ReactNode;
    metadata: ActiveMetadata | null;
    history: MetadataHistoryItem[];
    presets: PresetRules[];
    canEdit: boolean;
    hasTranscript: boolean;
    generating: boolean;
    /** "Generate more" titles were requested and haven't arrived yet. */
    titlesPending: boolean;
    onTitlesQueued: () => void;
};

/** Mirrors ReceiveStageResultRequest::MAX_TITLES on the server. */
const MAX_TITLES = 12;

/**
 * Edit the active generation's text fields (its chapters have their own tab;
 * both share one draft with the timeline), regenerate with another preset or
 * prompt, or go back to an older one.
 */
export default function MetadataPanel({
    recordId,
    heading,
    metadata,
    history,
    presets,
    canEdit,
    hasTranscript,
    generating,
    titlesPending,
    onTitlesQueued,
}: Props) {
    const { draft, dirty } = useMetadataDraft(metadata);
    const change = useMetadataDraftStore((state) => state.change);
    const preset =
        presets.find((item) => item.name === metadata?.preset) ?? presets[0];
    const readOnly = !canEdit || generating;
    const initialPreset = metadata?.preset ?? presets[0]?.name ?? 'generic';
    const descriptionLength = draft?.description?.length ?? 0;

    return (
        <EditorPanel
            title="Metadata"
            heading={heading}
            actions={
                metadata && (
                    <div className="flex items-center gap-1">
                        {canEdit && hasTranscript && (
                            <RegenerateDialog
                                recordId={recordId}
                                presets={presets}
                                initialPreset={initialPreset}
                                initialPrompt={metadata.custom_prompt ?? ''}
                                generating={generating}
                            />
                        )}
                        {history.length > 1 && (
                            <MetadataHistory
                                recordId={recordId}
                                history={history}
                                disabled={readOnly || dirty}
                            />
                        )}
                    </div>
                )
            }
        >
            <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-5">
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
                    !generating &&
                    (canEdit && hasTranscript ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                No metadata yet. Pick a platform and generate it
                                from the transcript.
                            </p>
                            <RegenerateForm
                                recordId={recordId}
                                presets={presets}
                                initialPreset={initialPreset}
                                initialPrompt=""
                                generating={generating}
                                hasMetadata={false}
                            />
                        </>
                    ) : (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            {hasTranscript
                                ? 'No metadata yet.'
                                : 'Metadata is generated once the transcript is ready.'}
                        </p>
                    ))
                ) : (
                    <div
                        className={cn(
                            'grid gap-6',
                            generating && 'pointer-events-none opacity-60',
                        )}
                    >
                        <div className="grid gap-1">
                            <TitleOptions
                                titles={draft.titles}
                                selected={draft.selectedTitle}
                                maxLength={preset?.title_max ?? null}
                                disabled={readOnly}
                                onChange={(titles, selectedTitle) =>
                                    change({ titles, selectedTitle })
                                }
                            />
                            {canEdit && (
                                <div className="flex justify-end">
                                    <GenerateTitlesButton
                                        recordId={recordId}
                                        metadataId={metadata.id}
                                        pending={titlesPending}
                                        onQueued={onTitlesQueued}
                                        disabled={
                                            readOnly ||
                                            !draft.summary ||
                                            draft.titles.length >= MAX_TITLES
                                        }
                                    />
                                </div>
                            )}
                        </div>

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

                        <ToneBadges
                            tone={draft.tone ?? null}
                            flags={draft.contentFlags ?? null}
                        />

                        <QuotesList
                            quotes={draft.quotes ?? []}
                            disabled={readOnly}
                            onChange={(quotes) => change({ quotes })}
                        />

                        <p className="text-xs text-muted-foreground">
                            Generated with {metadata.model}
                            {preset && ` for ${preset.label}`}
                        </p>
                    </div>
                )}
            </div>

            {canEdit && metadata && draft && (
                <MetadataSaveBar
                    recordId={recordId}
                    dirty={dirty}
                    generating={generating}
                />
            )}
        </EditorPanel>
    );
}
