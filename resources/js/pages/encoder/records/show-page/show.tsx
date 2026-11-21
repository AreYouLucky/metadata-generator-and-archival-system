import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useLoadMetadataDraft } from '@/hooks/use-metadata-draft';
import { usePlaybackShortcuts } from '@/hooks/use-playback-shortcuts';
import { useRecordChannel } from '@/hooks/use-record-channel';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { show } from '@/routes/records';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    ActiveMetadata,
    Contributor,
    ContributorRoleOption,
    MetadataHistoryItem,
    PresetRules,
    RecordProgressEvent,
    Speaker,
    StageState,
    TranscriptSegment,
    WorkspaceMedia,
    WorkspaceRecord,
    WorkspaceTranscript,
} from '@/types/records';
import ChaptersPanel from './partials/chapters-panel';
import CreditsPanel from './partials/credits-panel';
import InspectorTabBar from './partials/inspector-tab-bar';
import InspectorTabs from './partials/inspector-tabs';
import MetadataPanel from './partials/metadata-panel';
import PreviewMonitor from './partials/preview-monitor';
import Timeline from './partials/timeline';
import TranscriptPanel from './partials/transcript-panel';
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
};

type SavedSegment = Pick<TranscriptSegment, 'id'> & Partial<TranscriptSegment>;

/**
 * The workspace, laid out like a video editor: transcript on the left,
 * preview monitor in the middle and the inspector (metadata and credits) on
 * the right, above a timeline that takes 30% of the height. Status and
 * export sit in the app header beside the breadcrumbs. It stays dark in both
 * themes, like editing software, by scoping the `dark` theme to this
 * subtree. Space / K play and pause, J / L jump 5 s.
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
    const onSegmentDeleted = useCallback(
        (segmentId: number) =>
            setSegments((current) =>
                current.filter((segment) => segment.id !== segmentId),
            ),
        [],
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

    useRecordChannel(record.id, onProgress);
    usePlaybackShortcuts();

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
                            }}
                        />
                    </div>
                </div>

                <Timeline
                    recordId={record.id}
                    media={media}
                    segments={segments}
                    metadata={metadata}
                    canEdit={record.can_edit}
                    generating={generating}
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
