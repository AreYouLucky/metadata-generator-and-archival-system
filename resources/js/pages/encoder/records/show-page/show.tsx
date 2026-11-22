import { Head, router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import { useLoadMetadataDraft } from '@/hooks/use-metadata-draft';
import { useRecordChannel } from '@/hooks/use-record-channel';
import { useSetThumbnail } from '@/hooks/use-visuals';
import { translationLinesKey } from '@/hooks/use-translations';
import { getErrorMessage, languageName } from '@/lib/utilities';
import { useMetadataDraftStore } from '@/stores/metadata-draft-store';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { show } from '@/routes/records';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Archive, ArchiveOptions } from '@/types/archive';
import type { ExportSchema } from '@/types/export-schemas';
import type { WorkspaceReview } from '@/types/reviews';
import type {
    ActiveMetadata,
    AudioExport,
    Contributor,
    ContributorRoleOption,
    ExportFormatOption,
    MetadataHistoryItem,
    PresetRules,
    RecordProgressEvent,
    RecordTitlesGeneratedEvent,
    RecordTranslationUpdatedEvent,
    Speaker,
    StageState,
    TranscriptSegment,
    Translation,
    TranslationLanguage,
    WorkspaceMedia,
    WorkspaceRecord,
    WorkspaceTranscript,
} from '@/types/records';
import type { Visuals } from '@/types/visuals';
import ChaptersPanel from './partials/chapters-panel';
import ArchivePanel from './partials/archive-panel';
import CreditsPanel from './partials/credits-panel';
import ExportPanel from './partials/export-panel';
import InspectorTabBar from './partials/inspector-tab-bar';
import InspectorTabs from './partials/inspector-tabs';
import MetadataPanel from './partials/metadata-panel';
import PreviewMonitor from './partials/preview-monitor';
import Timeline from './partials/timeline';
import TranscriptPanel from './partials/transcript-panel';
import TranslationsPanel from './partials/translations-panel';
import VisualsPanel from './partials/visuals-panel';
import WorkspaceHeaderActions from './partials/workspace-header-actions';

type Props = {
    record: WorkspaceRecord;
    media: WorkspaceMedia;
    stages: StageState[];
    transcript: WorkspaceTranscript | null;
    speakers: Speaker[];
    contributors: Contributor[];
    contributorRoles: ContributorRoleOption[];
    metadata: ActiveMetadata | null;
    metadataHistory: MetadataHistoryItem[];
    presets: PresetRules[];
    translations: Translation[];
    translationLanguages: TranslationLanguage[];
    exportFormats: ExportFormatOption[];
    exportSchemas: ExportSchema[];
    archive: Archive;
    archiveOptions: ArchiveOptions;
    review: WorkspaceReview;
    visuals: Visuals;
    audioExports: AudioExport[];
};

type SavedSegment = Pick<TranscriptSegment, 'id'> & Partial<TranscriptSegment>;

/**
 * The workspace, laid out like a video editor: transcript on the left,
 * preview monitor in the middle and the inspector (metadata and credits) on
 * the right, above a timeline that takes 30% of the height. Status and
 * export sit in the app header beside the breadcrumbs. It stays dark in both
 * themes, like editing software, by scoping the `dark` theme to this
 * subtree. Keyboard shortcuts are listed in the shortcuts dialog ("?").
 */
export default function ShowRecord(props: Props) {
    const {
        record,
        media,
        transcript,
        speakers,
        contributors,
        contributorRoles,
        metadata,
        metadataHistory,
        presets,
        translationLanguages,
        exportFormats,
        exportSchemas,
        archive,
        archiveOptions,
        review,
        visuals,
        audioExports,
    } = props;
    const [stages, setStages] = useState(props.stages);
    const [processingStatus, setProcessingStatus] = useState(
        record.processing_status,
    );
    const reset = useWorkspaceStore((state) => state.reset);

    // A partial reload brings fresh props; they win over the live copies.
    useEffect(() => setStages(props.stages), [props.stages]);
    useEffect(
        () => setProcessingStatus(record.processing_status),
        [record.processing_status],
    );
    useEffect(() => reset, [reset]);
    useLoadMetadataDraft(metadata);

    // Timecode display uses the master's frame rate and start timecode.
    const setTimecodeBase = useWorkspaceStore((state) => state.setTimecodeBase);
    useEffect(
        () => setTimecodeBase(media.frame_rate, media.start_timecode),
        [media.frame_rate, media.start_timecode, setTimecodeBase],
    );

    // Translations change live (Reverb); a reload of the prop replaces them.
    const [translations, setTranslations] = useState(props.translations);
    useEffect(() => setTranslations(props.translations), [props.translations]);
    const upsertTranslation = useCallback((translation: Translation) => {
        setTranslations((current) =>
            current.some((item) => item.id === translation.id)
                ? current.map((item) =>
                      item.id === translation.id ? translation : item,
                  )
                : [...current, translation].sort((first, second) =>
                      first.language.localeCompare(second.language),
                  ),
        );
    }, []);
    const queryClient = useQueryClient();
    const onTranslation = useCallback(
        ({ translation }: RecordTranslationUpdatedEvent) => {
            upsertTranslation(translation);
            const name = languageName(translation.language);

            if (translation.status === 'done') {
                // New lines: the viewer and the caption track load them again.
                void queryClient.invalidateQueries({
                    queryKey: translationLinesKey(
                        record.id,
                        translation.language,
                    ),
                });
                useWorkspaceStore.getState().bumpCaptions();
                toast.success(`${name} translation is ready.`);
            } else if (translation.status === 'failed') {
                toast.error(
                    translation.error ?? `The ${name} translation failed.`,
                );
            }
        },
        [upsertTranslation, queryClient, record.id],
    );

    // "Generate more" titles arrive over Reverb, already saved.
    const [titlesPending, setTitlesPending] = useState(false);
    const appendTitles = useMetadataDraftStore((state) => state.appendTitles);
    const onTitles = useCallback(
        (event: RecordTitlesGeneratedEvent) => {
            setTitlesPending(false);

            if (event.error) {
                toast.error(event.error);

                return;
            }

            const count = event.titles.length;
            appendTitles(event.metadata_id, event.titles);
            toast.success(
                `${count} new title ${count === 1 ? 'option' : 'options'} added.`,
            );
            router.reload({ only: ['metadata'] });
        },
        [appendTitles],
    );

    // The lines every panel shows (transcript, timeline, current-line copy),
    // with edits and deletions applied; a reload of the transcript replaces them.
    const [segments, setSegments] = useState<TranscriptSegment[]>(
        transcript?.segments ?? [],
    );
    useEffect(() => setSegments(transcript?.segments ?? []), [transcript]);

    const onSegmentSaved = useCallback(
        (saved: SavedSegment) =>
            setSegments((current) =>
                current.map((segment) =>
                    segment.id === saved.id
                        ? { ...segment, ...saved }
                        : segment,
                ),
            ),
        [],
    );
    const bumpCaptions = useWorkspaceStore((state) => state.bumpCaptions);
    const onSegmentDeleted = useCallback(
        (segmentId: number) => {
            setSegments((current) =>
                current.filter((segment) => segment.id !== segmentId),
            );
            bumpCaptions();
        },
        [bumpCaptions],
    );
    const onSpeakerApplied = useCallback(
        (segmentIds: number[], speakerId: number | null) => {
            const changed = new Set(segmentIds);
            setSegments((current) =>
                current.map((segment) =>
                    changed.has(segment.id)
                        ? { ...segment, speaker_id: speakerId }
                        : segment,
                ),
            );
        },
        [],
    );

    const onProgress = useCallback((event: RecordProgressEvent) => {
        setProcessingStatus(event.processing_status);
        setStages((current) =>
            current.map((stage) =>
                stage.stage === event.stage.stage
                    ? { ...stage, ...event.stage }
                    : stage,
            ),
        );
    }, []);

    useRecordChannel(record.id, { onProgress, onTranslation, onTitles });
    // T: the frame the player shows becomes the thumbnail.
    const setThumbnail = useSetThumbnail(record.id);
    const thumbnailFromPlayer = () => {
        const player = useWorkspaceStore.getState().player;

        if (player) {
            setThumbnail.mutate(
                { timeSec: player.currentTime },
                {
                    onSuccess: () =>
                        toast.success('Thumbnail set to this frame.'),
                    onError: (error) =>
                        toast.error(
                            getErrorMessage(
                                error,
                                'The thumbnail could not be set.',
                            ),
                        ),
                },
            );
        }
    };
    useKeyboardShortcuts({
        segments,
        speakers,
        canEdit: record.can_edit,
        onSetThumbnail: thumbnailFromPlayer,
    });

    const finishedTranslations = useMemo(
        () => translations.filter((item) => item.status === 'done'),
        [translations],
    );
    const captionLanguages = useMemo(
        () =>
            transcript && segments.length > 0
                ? [
                      transcript.language,
                      ...finishedTranslations.map((item) => item.language),
                  ]
                : [],
        [transcript, segments.length, finishedTranslations],
    );
    const captionsVersion = useWorkspaceStore((state) => state.captionsVersion);
    // Saved metadata or transcript lines changed: the export preview refetches.
    const exportVersion = [
        metadata?.id ?? 0,
        metadata?.payload.titles.length ?? 0,
        captionsVersion,
        finishedTranslations.length,
    ].join('-');

    const generating = stages.some(
        (stage) =>
            stage.stage === 'generate_metadata' && stage.status === 'running',
    );
    const transcribing = stages.some(
        (stage) =>
            ['pending', 'running'].includes(stage.status) &&
            ['extract_audio', 'transcribe'].includes(stage.stage),
    );
    const initialPreset = metadata?.preset ?? presets[0]?.name ?? 'generic';
    const tabBar = (
        <InspectorTabBar
            metadata={metadata}
            creditCount={contributors.length}
            translationCount={translations.length}
            suggestionCount={
                archive.subjects.filter((item) => !item.is_confirmed).length +
                archive.keywords.filter((item) => !item.is_confirmed).length
            }
            frameCount={visuals.frames.length}
        />
    );

    return (
        <>
            <Head title={record.title} />
            <WorkspaceHeaderActions
                record={record}
                stages={stages}
                processingStatus={processingStatus}
                presets={presets}
                initialPreset={initialPreset}
                review={review}
                pendingSuggestions={
                    archive.subjects.filter((item) => !item.is_confirmed)
                        .length +
                    archive.keywords.filter((item) => !item.is_confirmed).length
                }
            />
            {/* data-workspace makes the page exactly the visible height on wide
                screens (app.css); the timeline takes 30% of it and the
                breadcrumbs plus the panels share the other 70%. */}
            <div
                data-workspace
                className="dark flex flex-1 flex-col bg-neutral-950 text-foreground xl:min-h-0 xl:overflow-hidden"
            >
                {/* One explicit row, so all three panels end on the same line
                    and scroll inside it, however long the transcript is. */}
                <div className="grid gap-px bg-white/10 xl:min-h-0 xl:flex-1 xl:grid-cols-[minmax(17rem,22rem)_minmax(0,1fr)_minmax(21rem,28rem)] xl:grid-rows-[minmax(0,1fr)]">
                    <div className="order-2 h-120 min-h-0 overflow-hidden bg-neutral-900 xl:order-0 xl:h-auto">
                        <TranscriptPanel
                            recordId={record.id}
                            transcript={transcript}
                            segments={segments}
                            speakers={speakers}
                            canEdit={record.can_edit}
                            transcribing={transcribing}
                            onSegmentSaved={onSegmentSaved}
                            onSegmentDeleted={onSegmentDeleted}
                            onSpeakerApplied={onSpeakerApplied}
                        />
                    </div>
                    <div className="order-1 min-h-0 overflow-hidden bg-neutral-950 xl:order-0">
                        <PreviewMonitor
                            recordId={record.id}
                            media={media}
                            contributors={contributors}
                            contributorRoles={contributorRoles}
                            canEdit={record.can_edit}
                            captionLanguages={captionLanguages}
                        />
                    </div>
                    <div className="order-3 h-144 min-h-0 overflow-hidden bg-neutral-900 xl:order-0 xl:h-auto">
                        <InspectorTabs
                            panels={{
                                metadata: (
                                    <MetadataPanel
                                        recordId={record.id}
                                        heading={tabBar}
                                        metadata={metadata}
                                        history={metadataHistory}
                                        presets={presets}
                                        canEdit={record.can_edit}
                                        hasTranscript={segments.length > 0}
                                        generating={generating}
                                        titlesPending={titlesPending}
                                        onTitlesQueued={() =>
                                            setTitlesPending(true)
                                        }
                                    />
                                ),
                                archive: (
                                    <ArchivePanel
                                        recordId={record.id}
                                        heading={tabBar}
                                        archive={archive}
                                        options={archiveOptions}
                                        suggestedLocation={
                                            metadata?.payload
                                                .suggestedLocation ?? null
                                        }
                                        canEdit={record.can_edit}
                                    />
                                ),
                                visuals: (
                                    <VisualsPanel
                                        recordId={record.id}
                                        heading={tabBar}
                                        visuals={visuals}
                                        stages={stages}
                                        canEdit={record.can_edit}
                                    />
                                ),
                                chapters: (
                                    <ChaptersPanel
                                        recordId={record.id}
                                        heading={tabBar}
                                        metadata={metadata}
                                        canEdit={record.can_edit}
                                        generating={generating}
                                    />
                                ),
                                credits: (
                                    <CreditsPanel
                                        recordId={record.id}
                                        heading={tabBar}
                                        contributors={contributors}
                                        roles={contributorRoles}
                                        canEdit={record.can_edit}
                                    />
                                ),
                                translations: (
                                    <TranslationsPanel
                                        recordId={record.id}
                                        heading={tabBar}
                                        translations={translations}
                                        languages={translationLanguages}
                                        originalLanguage={
                                            transcript?.language ?? null
                                        }
                                        canEdit={record.can_edit}
                                        hasTranscript={segments.length > 0}
                                        onStarted={upsertTranslation}
                                    />
                                ),
                                export: (
                                    <ExportPanel
                                        recordId={record.id}
                                        audioExports={audioExports}
                                        formats={exportFormats}
                                        schemas={exportSchemas}
                                        originalLanguage={
                                            transcript?.language ?? null
                                        }
                                        translations={translations}
                                        version={exportVersion}
                                    />
                                ),
                            }}
                        />
                    </div>
                </div>

                <Timeline
                    recordId={record.id}
                    media={media}
                    segments={segments}
                    metadata={metadata}
                    shots={visuals.shots}
                    scenes={visuals.scenes}
                    canEdit={record.can_edit}
                    generating={generating}
                    onSegmentSaved={onSegmentSaved}
                />
            </div>
        </>
    );
}

/**
 * Records are filed under projects (there is no record list), so the trail
 * follows the project and series. Series have no page of their own; that
 * crumb opens the project, which lists them.
 */
ShowRecord.layout = ({ record }: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        ...(record.project
            ? [
                  {
                      title: record.project.title,
                      href: showProject(record.project.id),
                  },
                  ...(record.series
                      ? [
                            {
                                title: record.series,
                                href: showProject(record.project.id),
                            },
                        ]
                      : []),
              ]
            : []),
        { title: record.title, href: show(record.id) },
    ],
});
