import { CalendarClock, Sparkles, Undo2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { useSuggestDescriptive, useUpdateRecord } from '@/hooks/use-archive';
import { Spinner } from '@/components/ui/spinner';
import { useWorkspaceStore } from '@/stores/workspace-store';
import { useProjectOptions } from '@/hooks/use-projects';
import { formatDate, getErrorMessage } from '@/lib/utilities';
import type {
    Archive,
    ArchiveDescriptive,
    ArchiveOptions,
} from '@/types/archive';
import ArchiveField from './archive-field';
import ArchiveKeywords from './archive-keywords';
import ArchiveSaveBar from './archive-save-bar';
import ArchiveSelect from './archive-select';
import ArchiveSubjects from './archive-subjects';

type Props = {
    recordId: string;
    archive: Archive;
    options: ArchiveOptions;
    canEdit: boolean;
};

const toNumber = (value: string) => (value === '' ? null : Number(value));

/**
 * What the video is: titles, description, genre and type, language, dates,
 * season and episode, project and series, then subjects and keywords (AI
 * suggestions wait for confirmation).
 */
export default function ArchiveDescriptive({
    recordId,
    archive,
    options,
    canEdit,
}: Props) {
    const { values, change, dirty, errors, feedback } = useArchiveForm(
        archive.descriptive,
    );
    const save = useUpdateRecord(recordId);
    const projects = useProjectOptions('');
    const project = projects.data?.find(
        (item) => item.id === values.project_id,
    );
    const containerDate = archive.suggestions.date_recorded;
    const disabled = !canEdit;

    // "Fill with AI": suggestions fill the form (unsaved); Undo restores it.
    const suggest = useSuggestDescriptive(recordId);
    const pending = useWorkspaceStore((state) => state.descriptivePending);
    const setPending = useWorkspaceStore(
        (state) => state.setDescriptivePending,
    );
    const suggestion = useWorkspaceStore(
        (state) => state.descriptiveSuggestion,
    );
    const clearSuggestion = useWorkspaceStore(
        (state) => state.setDescriptiveSuggestion,
    );
    const [before, setBefore] = useState<ArchiveDescriptive | null>(null);
    const [filled, setFilled] = useState(0);

    // Once saved (or discarded by a reload) there is nothing left to undo.
    useEffect(() => {
        if (!dirty) {
            setBefore(null);
        }
    }, [dirty]);

    useEffect(() => {
        if (!suggestion) {
            return;
        }

        const changes = Object.fromEntries(
            Object.entries(suggestion).filter(
                ([key, value]) =>
                    value !== null &&
                    value !== undefined &&
                    value !== '' &&
                    value !== values[key as keyof ArchiveDescriptive],
            ),
        ) as Partial<ArchiveDescriptive>;

        if (changes.date_recorded) {
            changes.date_recorded_source = 'ai';
            changes.date_recorded_precision ??=
                suggestion.date_recorded_precision ?? 'circa';
        }

        setBefore(values);
        setFilled(Object.keys(changes).length);
        change(changes);
        clearSuggestion(null);
        toast.success(
            Object.keys(changes).length > 0
                ? 'Suggestions filled in. Review them, then Save.'
                : 'The AI had nothing new to suggest.',
        );
        // Applied once per arrival; the form values are read at that moment.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [suggestion]);

    return (
        <div className="grid gap-4">
            {canEdit && (
                <div className="flex flex-wrap items-center gap-2 rounded-md bg-brand-muted/60 p-2.5 text-xs ring-1 ring-brand/30">
                    <Sparkles
                        className="size-4 shrink-0 text-brand"
                        aria-hidden
                    />
                    <p className="min-w-0 flex-1">
                        {before && filled > 0
                            ? `The AI filled ${filled} ${filled === 1 ? 'field' : 'fields'}. Review them, then Save.`
                            : 'Let the AI fill the title, description, genre, type, country and date from the video’s summary and its event.'}
                    </p>
                    {before && filled > 0 && (
                        <Button
                            size="sm"
                            variant="ghost"
                            className="h-7"
                            onClick={() => {
                                change(before);
                                setBefore(null);
                            }}
                        >
                            <Undo2 aria-hidden />
                            Undo
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="secondary"
                        className="h-7"
                        disabled={pending || suggest.isPending}
                        onClick={() =>
                            suggest.mutate(undefined, {
                                onSuccess: () => setPending(true),
                                onError: (error) =>
                                    toast.error(
                                        getErrorMessage(
                                            error,
                                            'Suggestions could not be requested.',
                                        ),
                                    ),
                            })
                        }
                    >
                        {pending || suggest.isPending ? (
                            <Spinner />
                        ) : (
                            <Sparkles aria-hidden />
                        )}
                        {pending ? 'Writing…' : 'Fill with AI'}
                    </Button>
                </div>
            )}

            <ArchiveField
                label="Title"
                htmlFor="archive-title"
                error={errors.title}
            >
                <Input
                    id="archive-title"
                    value={values.title}
                    maxLength={250}
                    disabled={disabled}
                    onChange={(event) => change({ title: event.target.value })}
                />
            </ArchiveField>
            <ArchiveField
                label="Alternative title"
                htmlFor="archive-alt-title"
                error={errors.alternative_title}
            >
                <Input
                    id="archive-alt-title"
                    value={values.alternative_title ?? ''}
                    maxLength={250}
                    disabled={disabled}
                    onChange={(event) =>
                        change({
                            alternative_title: event.target.value || null,
                        })
                    }
                />
            </ArchiveField>
            <ArchiveField
                label="Description"
                htmlFor="archive-description"
                hint="The catalogue description (the AI's is in the Metadata tab)."
                error={errors.description}
            >
                <Textarea
                    id="archive-description"
                    rows={4}
                    value={values.description ?? ''}
                    maxLength={10000}
                    disabled={disabled}
                    onChange={(event) =>
                        change({ description: event.target.value || null })
                    }
                />
            </ArchiveField>

            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField label="Genre" htmlFor="archive-genre">
                    <ArchiveSelect
                        id="archive-genre"
                        value={
                            values.genre_id === null
                                ? null
                                : String(values.genre_id)
                        }
                        options={options.genres.map((genre) => ({
                            value: String(genre.id),
                            label: genre.name,
                        }))}
                        emptyLabel="None"
                        disabled={disabled}
                        onChange={(value) =>
                            change({
                                genre_id: value === null ? null : Number(value),
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField label="Video type" htmlFor="archive-video-type">
                    <ArchiveSelect
                        id="archive-video-type"
                        value={
                            values.video_type_id === null
                                ? null
                                : String(values.video_type_id)
                        }
                        options={options.videoTypes.map((type) => ({
                            value: String(type.id),
                            label: type.name,
                        }))}
                        emptyLabel="None"
                        disabled={disabled}
                        onChange={(value) =>
                            change({
                                video_type_id:
                                    value === null ? null : Number(value),
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Language code"
                    htmlFor="archive-language"
                    hint="e.g. en, fil, ceb"
                    error={errors.language}
                >
                    <Input
                        id="archive-language"
                        value={values.language ?? ''}
                        maxLength={16}
                        disabled={disabled}
                        onChange={(event) =>
                            change({
                                language:
                                    event.target.value.trim().toLowerCase() ||
                                    null,
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Country of origin"
                    htmlFor="archive-country"
                    error={errors.country_origin}
                >
                    <Input
                        id="archive-country"
                        value={values.country_origin ?? ''}
                        maxLength={64}
                        placeholder="PH"
                        disabled={disabled}
                        onChange={(event) =>
                            change({
                                country_origin: event.target.value || null,
                            })
                        }
                    />
                </ArchiveField>
            </div>

            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField
                    label="Date recorded"
                    htmlFor="archive-date-recorded"
                    error={errors.date_recorded}
                >
                    <Input
                        id="archive-date-recorded"
                        type="date"
                        value={values.date_recorded ?? ''}
                        disabled={disabled}
                        onChange={(event) =>
                            change({
                                date_recorded: event.target.value || null,
                                date_recorded_source: event.target.value
                                    ? 'manual'
                                    : null,
                                date_recorded_precision: event.target.value
                                    ? (values.date_recorded_precision ?? 'day')
                                    : null,
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="How exact"
                    htmlFor="archive-date-precision"
                    error={errors.date_recorded_precision}
                >
                    <ArchiveSelect
                        id="archive-date-precision"
                        value={values.date_recorded_precision}
                        options={options.datePrecisions}
                        emptyLabel="—"
                        disabled={disabled || !values.date_recorded}
                        onChange={(value) =>
                            change({ date_recorded_precision: value })
                        }
                    />
                </ArchiveField>
            </div>
            {containerDate &&
                containerDate !== values.date_recorded &&
                canEdit && (
                    <div className="flex items-start gap-2 rounded-md bg-amber-500/10 p-2.5 text-xs text-amber-100 ring-1 ring-amber-400/20">
                        <CalendarClock
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        <p className="flex-1">
                            The file says it was created on{' '}
                            {formatDate(containerDate)}. That is often the copy
                            or encode date, not the recording date, so check
                            before using it.
                        </p>
                        <Button
                            size="sm"
                            variant="secondary"
                            className="h-7"
                            onClick={() =>
                                change({
                                    date_recorded: containerDate,
                                    date_recorded_precision: 'day',
                                    date_recorded_source: 'container_tag',
                                })
                            }
                        >
                            Use
                        </Button>
                    </div>
                )}

            <div className="grid gap-4 @md:grid-cols-3">
                <ArchiveField
                    label="Date published"
                    htmlFor="archive-date-published"
                    error={errors.date_published}
                >
                    <Input
                        id="archive-date-published"
                        type="date"
                        value={values.date_published ?? ''}
                        disabled={disabled}
                        onChange={(event) =>
                            change({
                                date_published: event.target.value || null,
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Season"
                    htmlFor="archive-season"
                    error={errors.season_number}
                >
                    <Input
                        id="archive-season"
                        type="number"
                        min={1}
                        value={values.season_number ?? ''}
                        disabled={disabled}
                        onChange={(event) =>
                            change({
                                season_number: toNumber(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Episode"
                    htmlFor="archive-episode"
                    error={errors.episode_number}
                >
                    <Input
                        id="archive-episode"
                        type="number"
                        min={1}
                        value={values.episode_number ?? ''}
                        disabled={disabled}
                        onChange={(event) =>
                            change({
                                episode_number: toNumber(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
            </div>

            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField
                    label="Project (event)"
                    htmlFor="archive-project"
                    error={errors.project_id}
                >
                    <ArchiveSelect
                        id="archive-project"
                        value={values.project_id}
                        options={(projects.data ?? []).map((item) => ({
                            value: item.id,
                            label: item.title,
                        }))}
                        disabled={disabled || projects.isPending}
                        onChange={(value) =>
                            change({
                                project_id: value,
                                project_series_id: null,
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Series"
                    htmlFor="archive-series"
                    error={errors.project_series_id}
                >
                    <ArchiveSelect
                        id="archive-series"
                        value={values.project_series_id}
                        options={(project?.series ?? []).map((series) => ({
                            value: series.id,
                            label: series.title,
                        }))}
                        emptyLabel="No series"
                        disabled={disabled || !project}
                        onChange={(value) =>
                            change({ project_series_id: value })
                        }
                    />
                </ArchiveField>
            </div>

            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(values, feedback('Description saved.'))
                    }
                />
            )}

            <ArchiveSubjects
                recordId={recordId}
                subjects={archive.subjects}
                types={options.subjectTypes}
                canEdit={canEdit}
            />
            <ArchiveKeywords
                recordId={recordId}
                keywords={archive.keywords}
                canEdit={canEdit}
            />
        </div>
    );
}
