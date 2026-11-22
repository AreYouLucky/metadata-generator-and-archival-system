import { Head, router } from '@inertiajs/react';
import { DEFAULT_VISUAL_ANALYSIS } from '@/components/encoder/visual-analysis-options';
import { Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useLocalVideo } from '@/hooks/use-local-video';
import { useUploadRecord } from '@/hooks/use-upload-record';
import { formatNumber, getErrorMessage } from '@/lib/utilities';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { create } from '@/routes/records';
import type { ProjectOption } from '@/types/projects';
import type { PresetName, PresetRules } from '@/types/records';
import type { DailyLimit } from '@/types/usage';
import ImportPreview from './partials/import-preview';
import ProjectPicker from './partials/project-picker';
import MediaBin from './partials/media-bin';
import UploadOptions from './partials/upload-options';
import type { UploadOptionValues } from './partials/upload-options';
import UploadStatusBar from './partials/upload-status-bar';
import type { UploadProgress } from './partials/upload-status-bar';

type Props = {
    initialProject: ProjectOption | null;
    /** Preselected series of `initialProject` (from a series row). */
    initialSeriesId: string | null;
    presets: PresetRules[];
    limits: { max_upload_mb: number; max_video_minutes: number };
    /** Today's processing allowance (checked again on the server). */
    dailyLimit: DailyLimit | null;
};

/**
 * "Import media", laid out like an editor: media bin on the left, a preview
 * of the chosen clip in the middle, import settings in the inspector on the
 * right, and the upload progress in the status bar.
 */
export default function CreateRecord({
    initialProject,
    initialSeriesId,
    presets,
    limits,
    dailyLimit,
}: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [project, setProject] = useState<ProjectOption | null>(
        initialProject,
    );
    const [seriesId, setSeriesId] = useState<string | null>(initialSeriesId);
    const [progress, setProgress] = useState<UploadProgress | null>(null);
    const [options, setOptions] = useState<UploadOptionValues>({
        title: '',
        preset: (presets[0]?.name ?? 'generic') as PresetName,
        customPrompt: '',
        expectedMd5: '',
        expectedSha256: '',
        ...DEFAULT_VISUAL_ANALYSIS,
    });
    const startedAt = useRef(0);
    const video = useLocalVideo(file);
    const upload = useUploadRecord();
    const tooLarge =
        file !== null && file.size > limits.max_upload_mb * 1024 * 1024;
    const tooLong =
        video.duration !== null &&
        video.duration > limits.max_video_minutes * 60;
    const limitReached =
        dailyLimit?.remaining !== null &&
        dailyLimit?.remaining !== undefined &&
        dailyLimit.remaining <= 0;

    const start = () => {
        if (!file || !project || tooLarge || tooLong || limitReached) {
            return;
        }

        startedAt.current = performance.now();
        setProgress({ fraction: 0, startedAt: startedAt.current });
        upload.mutate(
            {
                file,
                projectId: project.id,
                seriesId,
                ...options,
                onProgress: (fraction) =>
                    setProgress({ fraction, startedAt: startedAt.current }),
            },
            {
                onSuccess: (record) => {
                    toast.success('Upload complete. Processing has started.');
                    router.visit(record.url);
                },
                onError: (error) => {
                    setProgress(null);
                    toast.error(getErrorMessage(error, 'The upload failed.'));
                },
            },
        );
    };

    return (
        <>
            <Head title="New record" />
            <div className="dark flex flex-1 flex-col bg-neutral-950 text-foreground xl:h-[calc(100svh-4rem)] xl:min-h-[36rem] xl:overflow-hidden">
                <header className="flex min-h-12 flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-neutral-900 px-3 py-2">
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
                                    className={`text-xs ${limitReached ? 'text-amber-300' : 'text-muted-foreground'}`}
                                    title="Transcription minutes you can still process today"
                                >
                                    {limitReached
                                        ? `Today's ${dailyLimit.limit}-minute processing limit is used up`
                                        : `${formatNumber(dailyLimit.remaining ?? 0, 1)} of ${dailyLimit.limit} min left today`}
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
                            disabled={upload.isPending}
                        >
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            onClick={start}
                            disabled={
                                !file ||
                                !project ||
                                tooLarge ||
                                tooLong ||
                                limitReached ||
                                upload.isPending
                            }
                        >
                            {upload.isPending && <Spinner />}
                            Upload and process
                        </Button>
                    </div>
                </header>

                <div className="grid min-h-0 flex-1 gap-px bg-white/10 xl:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)_minmax(19rem,24rem)]">
                    <div className="min-h-0 bg-neutral-900">
                        <MediaBin
                            file={file}
                            video={video}
                            onFile={setFile}
                            disabled={upload.isPending}
                            maxUploadMb={limits.max_upload_mb}
                            maxVideoMinutes={limits.max_video_minutes}
                            tooLarge={tooLarge}
                            tooLong={tooLong}
                        />
                    </div>
                    <div className="min-h-72 bg-neutral-950">
                        <ImportPreview file={file} video={video} />
                    </div>
                    <div className="min-h-0 bg-neutral-900">
                        <UploadOptions
                            presets={presets}
                            values={options}
                            onChange={setOptions}
                            disabled={upload.isPending}
                        >
                            <ProjectPicker
                                project={project}
                                seriesId={seriesId}
                                disabled={upload.isPending}
                                onChange={(nextProject, nextSeries) => {
                                    setProject(nextProject);
                                    setSeriesId(nextSeries);
                                }}
                            />
                        </UploadOptions>
                    </div>
                </div>

                <UploadStatusBar
                    file={file}
                    progress={progress}
                    pending={upload.isPending}
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
