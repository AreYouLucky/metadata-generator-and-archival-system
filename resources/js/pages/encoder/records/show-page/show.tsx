import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useRecordChannel } from '@/hooks/use-record-channel';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import { show } from '@/routes/records';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    ActiveMetadata,
    MetadataHistoryItem,
    PresetRules,
    RecordProgressEvent,
    StageState,
    WorkspaceMedia,
    WorkspaceRecord,
    WorkspaceTranscript,
} from '@/types/records';
import CurrentLineCopy from './partials/current-line-copy';
import EditorToolbar from './partials/editor-toolbar';
import MetadataPanel from './partials/metadata-panel';
import PipelineProgress from './partials/pipeline-progress';
import PreviewMonitor from './partials/preview-monitor';
import Timeline from './partials/timeline';
import TranscriptPanel from './partials/transcript-panel';

type Props = {
    record: WorkspaceRecord;
    media: WorkspaceMedia;
    stages: StageState[];
    transcript: WorkspaceTranscript | null;
    metadata: ActiveMetadata | null;
    metadataHistory: MetadataHistoryItem[];
    presets: PresetRules[];
};

/**
 * The workspace, laid out like a video editor: transcript on the left,
 * preview monitor in the middle, metadata inspector on the right and the
 * timeline along the bottom. It stays dark in both themes, like editing
 * software, by scoping the `dark` theme to this subtree.
 */
export default function ShowRecord(props: Props) {
    const { record, media, transcript, metadata, metadataHistory, presets } =
        props;
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

    const generating = stages.some(
        (stage) =>
            stage.stage === 'generate_metadata' && stage.status === 'running',
    );
    const transcribing = stages.some(
        (stage) =>
            ['pending', 'running'].includes(stage.status) &&
            ['extract_audio', 'transcribe'].includes(stage.stage),
    );
    const segments = transcript?.segments ?? [];

    return (
        <>
            <Head title={record.title} />
            <div className="dark flex flex-1 flex-col bg-neutral-950 text-foreground xl:h-[calc(100svh-4rem)] xl:min-h-[40rem] xl:overflow-hidden">
                <EditorToolbar
                    record={record}
                    processingStatus={processingStatus}
                />
                <PipelineProgress
                    recordId={record.id}
                    stages={stages}
                    processingStatus={processingStatus}
                    presets={presets}
                    initialPreset={
                        metadata?.preset ?? presets[0]?.name ?? 'generic'
                    }
                    canEdit={record.can_edit}
                />

                <div className="grid min-h-0 flex-1 gap-px bg-white/10 xl:grid-cols-[minmax(17rem,22rem)_minmax(0,1fr)_minmax(19rem,26rem)]">
                    <div className="order-2 min-h-[24rem] bg-neutral-900 xl:order-none xl:min-h-0">
                        <TranscriptPanel
                            recordId={record.id}
                            transcript={transcript}
                            canEdit={record.can_edit}
                            transcribing={transcribing}
                        />
                    </div>
                    <div className="order-1 min-h-0 bg-neutral-950 xl:order-none">
                        <PreviewMonitor media={media} />
                    </div>
                    <div className="order-3 min-h-0 bg-neutral-900 xl:order-none">
                        <MetadataPanel
                            key={metadata?.id ?? 'none'}
                            recordId={record.id}
                            metadata={metadata}
                            history={metadataHistory}
                            presets={presets}
                            canEdit={record.can_edit}
                            hasTranscript={segments.length > 0}
                            generating={generating}
                        />
                    </div>
                </div>

                <Timeline
                    recordId={record.id}
                    media={media}
                    segments={segments}
                    chapters={metadata?.payload.chapters ?? []}
                />
            </div>
            <CurrentLineCopy segments={segments} />
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
