import { Head, router } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { DEFAULT_VISUAL_ANALYSIS } from '@/components/encoder/visual-analysis-options';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useImportMedia } from '@/hooks/use-import-media';
import type { BinItem } from '@/hooks/use-import-media';
import { formatNumber, getErrorMessage } from '@/lib/utilities';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { create } from '@/routes/records';
import type { ProjectOption } from '@/types/projects';
import type { AiProviders, PresetName, PresetRules } from '@/types/records';
import type { DailyLimit } from '@/types/usage';
import ImportPreview from './partials/import-preview';
import MediaBin from './partials/media-bin';
import ProjectPicker from './partials/project-picker';
import UploadOptions from './partials/upload-options';
import type { UploadOptionValues } from './partials/upload-options';
import UploadStatusBar from './partials/upload-status-bar';
import type { UploadProgress } from './partials/upload-status-bar';

type Props = {
    initialProject: ProjectOption | null;
    /** Preselected series of `initialProject` (from a series row). */
    initialSeriesId: string | null;
    presets: PresetRules[];
    limits: {
        max_upload_mb: number;
        max_video_minutes: number;
        max_batch_size: number;
    };
    /** Today's processing allowance (checked again on the server). */
    dailyLimit: DailyLimit | null;
    /** Deferred: which providers hear the audio, for the privacy notice. */
    providers?: AiProviders;
};

/**
 * "Import media", laid out like an editor: media bin on the left (files and
 * web addresses), a preview of the chosen clip in the middle, import
 * settings in the inspector on the right, and the upload progress in the
 * status bar. One clip makes a record; several make a batch.
 */
export default function CreateRecord({
    initialProject,
    initialSeriesId,
    presets,
    limits,
    dailyLimit,
    providers,
}: Props) {
    const [items, setItems] = useState<BinItem[]>([]);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [durations, setDurations] = useState<Record<string, number | null>>(
        {},
    );
    const [batchName, setBatchName] = useState('');
    const [project, setProject] = useState<ProjectOption | null>(
        initialProject,
    );
    const [seriesId, setSeriesId] = useState<string | null>(initialSeriesId);
    const [progress, setProgress] = useState<UploadProgress | null>(null);
    const [options, setOptions] = useState<UploadOptionValues>({
        preset: (presets[0]?.name ?? 'generic') as PresetName,
        customPrompt: '',
        expectedMd5: '',
        expectedSha256: '',
        diarization: false,
        ...DEFAULT_VISUAL_ANALYSIS,
    });
    const startedAt = useRef(0);
    const importMedia = useImportMedia();
    const pending = importMedia.isPending;

    const selected = items.find((item) => item.id === selectedId) ?? null;
    const invalidIds = useMemo(
        () =>
            new Set(
                items
                    .filter(
                        (item) =>
                            item.kind === 'file' &&
                            (item.file.size >
                                limits.max_upload_mb * 1024 * 1024 ||
                                (durations[item.id] ?? 0) >
                                    limits.max_video_minutes * 60),
                    )
                    .map((item) => item.id),
            ),
        [items, durations, limits],
    );
    const totalBytes = items.reduce(
        (sum, item) => sum + (item.kind === 'file' ? item.file.size : 0),
        0,
    );
    const limitReached =
        dailyLimit?.remaining !== null &&
        dailyLimit?.remaining !== undefined &&
        dailyLimit.remaining <= 0;
    const canStart =
        items.length > 0 &&
        project !== null &&
        invalidIds.size === 0 &&
        !limitReached &&
        !pending;

    const add = useCallback(
        (added: BinItem[]) => {
            setItems((current) => {
                const room = limits.max_batch_size - current.length;

                if (added.length > room) {
                    toast.warning(
                        'A batch holds at most ' +
                            limits.max_batch_size +
                            ' videos.',
                    );
                }

                return [...current, ...added.slice(0, Math.max(0, room))];
            });
            setSelectedId((current) => current ?? added[0]?.id ?? null);
        },
        [limits.max_batch_size],
    );
    const addFiles = useCallback(
        (files: File[]) =>
            add(
                files.map((file) => ({
                    id: crypto.randomUUID(),
                    kind: 'file' as const,
                    file,
                    title: '',
                })),
            ),
        [add],
    );
    const addUrl = useCallback(
        (url: string) =>
            add([{ id: crypto.randomUUID(), kind: 'url', url, title: '' }]),
        [add],
    );
    const measured = useCallback(
        (id: string, duration: number | null) =>
            setDurations((current) =>
                current[id] === duration
                    ? current
                    : { ...current, [id]: duration },
            ),
        [],
    );
    const remove = (id: string) => {
        const rest = items.filter((item) => item.id !== id);
        setItems(rest);
        setSelectedId((current) =>
            current === id ? (rest[0]?.id ?? null) : current,
        );
    };
    const setTitle = (title: string) =>
        setItems((current) =>
            current.map((item) =>
                item.id === selectedId ? { ...item, title } : item,
            ),
        );

    const start = () => {
        if (!canStart || !project) {
            return;
        }

        startedAt.current = performance.now();
        setProgress({ sent: 0, startedAt: startedAt.current, itemIndex: 0 });
        importMedia.mutate(
            {
                items,
                batchName,
                projectId: project.id,
                seriesId,
                options: {
                    preset: options.preset,
                    custom_prompt: options.customPrompt,
                    visual_analysis: options.visualAnalysis,
                    ocr: options.ocr,
                    object_detection: options.objectDetection,
                    diarization: options.diarization,
                },
                expectedMd5: options.expectedMd5,
                expectedSha256: options.expectedSha256,
                onProgress: (sent, itemIndex) =>
                    setProgress({
                        sent,
                        itemIndex,
                        startedAt: startedAt.current,
                    }),
            },
            {
                onSuccess: (created) => {
                    toast.success(
                        items.length > 1
                            ? 'Batch created. Processing has started.'
                            : 'Import started. Processing follows.',
                    );
                    router.visit(created.url);
                },
                onError: (error) => {
                    setProgress(null);
                    toast.error(getErrorMessage(error, 'The import failed.'));
                },
            },
        );
    };

    return (
        <>
            <Head title="New record" />
            <div className="flex flex-1 flex-col bg-background text-foreground xl:h-[calc(100svh-4rem)] xl:min-h-[36rem] xl:overflow-hidden">
                <header className="flex min-h-12 flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-3 py-2">
                    <div className="flex items-center gap-3">
                        <span className="flex size-7 items-center justify-center rounded-md bg-brand text-brand-foreground">
                            <Upload className="size-4" aria-hidden />
                        </span>
                        <h1 className="text-sm font-semibold">Import media</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        {dailyLimit?.limit !== null &&
                            dailyLimit?.limit !== undefined && (
                                <span
                                    className={
                                        limitReached
                                            ? 'text-xs text-amber-700'
                                            : 'text-xs text-muted-foreground'
                                    }
                                    title="Transcription minutes you can still process today"
                                >
                                    {limitReached
                                        ? "Today's " +
                                          dailyLimit.limit +
                                          '-minute processing limit is used up'
                                        : formatNumber(
                                              dailyLimit.remaining ?? 0,
                                              1,
                                          ) +
                                          ' of ' +
                                          dailyLimit.limit +
                                          ' min left today'}
                                </span>
                            )}
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.visit(
                                    initialProject
                                        ? showProject.url(initialProject.id)
                                        : projectsIndex.url(),
                                )
                            }
                            disabled={pending}
                        >
                            Cancel
                        </Button>
                        <Button size="sm" onClick={start} disabled={!canStart}>
                            {pending && <Spinner />}
                            {items.length > 1
                                ? 'Import ' + items.length + ' as a batch'
                                : 'Upload and process'}
                        </Button>
                    </div>
                </header>

                <div className="grid min-h-0 flex-1 gap-px bg-muted xl:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)_minmax(19rem,24rem)]">
                    <div className="min-h-0 bg-card">
                        <MediaBin
                            items={items}
                            selectedId={selectedId}
                            invalidIds={invalidIds}
                            onAddFiles={addFiles}
                            onAddUrl={addUrl}
                            onSelect={setSelectedId}
                            onRemove={remove}
                            onMeasured={measured}
                            disabled={pending}
                            maxUploadMb={limits.max_upload_mb}
                            maxVideoMinutes={limits.max_video_minutes}
                            maxBatchSize={limits.max_batch_size}
                        />
                    </div>
                    <div className="min-h-72 bg-background">
                        <ImportPreview item={selected} />
                    </div>
                    <div className="min-h-0 bg-card">
                        <UploadOptions
                            presets={presets}
                            values={options}
                            onChange={setOptions}
                            title={selected ? selected.title : null}
                            onTitleChange={setTitle}
                            batchName={items.length > 1 ? batchName : null}
                            onBatchNameChange={setBatchName}
                            checksumsAvailable={
                                items.length === 1 && items[0].kind === 'file'
                            }
                            hasUrls={items.some((item) => item.kind === 'url')}
                            providers={providers}
                            disabled={pending}
                        >
                            <ProjectPicker
                                project={project}
                                seriesId={seriesId}
                                disabled={pending}
                                onChange={(nextProject, nextSeries) => {
                                    setProject(nextProject);
                                    setSeriesId(nextSeries);
                                }}
                            />
                        </UploadOptions>
                    </div>
                </div>

                <UploadStatusBar
                    itemCount={items.length}
                    totalBytes={totalBytes}
                    progress={progress}
                    pending={pending}
                    projectChosen={project !== null}
                />
            </div>
        </>
    );
}

/**
 * Uploads start from a project or one of its series, so the trail follows
 * them (there is no record list). Series have no page of their own; that
 * crumb opens the project.
 */
CreateRecord.layout = ({ initialProject, initialSeriesId }: Props) => {
    const series = initialProject?.series.find(
        (item) => item.id === initialSeriesId,
    );

    return {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex() },
            ...(initialProject
                ? [
                      {
                          title: initialProject.title,
                          href: showProject(initialProject.id),
                      },
                      ...(series
                          ? [
                                {
                                    title: series.title,
                                    href: showProject(initialProject.id),
                                },
                            ]
                          : []),
                  ]
                : []),
            {
                title: 'Upload video',
                href: create({
                    query: {
                        project: initialProject?.id ?? null,
                        series: series?.id ?? null,
                    },
                }),
            },
        ],
    };
};
