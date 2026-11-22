import { Link } from '@inertiajs/react';
import { Download, Layers, RotateCcw, Youtube } from 'lucide-react';
import { useState } from 'react';
import HeaderActions from '@/components/all/header-actions';
import ClassificationBadge from '@/components/all/classification-badge';
import EmbargoBadge from '@/components/encoder/embargo-badge';
import { Button } from '@/components/ui/button';
import { show as showBatch } from '@/routes/batches';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    PresetRules,
    ProcessingStatus,
    StageState,
    WorkspaceRecord,
} from '@/types/records';
import type { WorkspaceReview } from '@/types/reviews';
import CancelProcessingDialog from './cancel-processing-dialog';
import DeleteRecordDialog from './delete-record-dialog';
import ProcessingStatusMenu from './processing-status-menu';
import RetryProcessingDialog from './retry-processing-dialog';
import ReviewStatusDialog from './review-status-dialog';
import ShortcutsDialog from './shortcuts-dialog';
import SubmitRecordDialog from './submit-record-dialog';

type Props = {
    record: WorkspaceRecord;
    stages: StageState[];
    processingStatus: ProcessingStatus;
    presets: PresetRules[];
    review: WorkspaceReview;
    pendingSuggestions: number;
};

/**
 * The workspace's controls in the app header, beside the breadcrumbs (which
 * already name the project, series and record): processing status (with
 * "Retry" on a failed or skipped step), cancel while it runs, retry after a
 * failure or cancel, the batch it came in, review status (notes, history,
 * admin actions), "Submit for approval", keyboard shortcuts, Export and
 * Publish (which open their panels in the inspector, and close them again)
 * and Delete.
 */
export default function WorkspaceHeaderActions({
    record,
    stages,
    processingStatus,
    presets,
    review,
    pendingSuggestions,
}: Props) {
    const [retryOpen, setRetryOpen] = useState(false);
    const [retryStage, setRetryStage] = useState<StageState | null>(null);
    const setInspectorTab = useWorkspaceStore((state) => state.setInspectorTab);
    const exportOpen = useWorkspaceStore(
        (state) => state.inspectorTab === 'export',
    );
    const publishOpen = useWorkspaceStore(
        (state) => state.inspectorTab === 'publish',
    );
    const working =
        processingStatus === 'queued' || processingStatus === 'processing';
    const openRetry = (stage: StageState | null) => {
        setRetryStage(stage);
        setRetryOpen(true);
    };

    return (
        <HeaderActions>
            {stages.length > 0 && (
                <ProcessingStatusMenu
                    stages={stages}
                    processingStatus={processingStatus}
                    onRetryStage={record.can_edit ? openRetry : undefined}
                />
            )}
            {working && record.can_cancel && (
                <CancelProcessingDialog recordId={record.id} />
            )}
            {(processingStatus === 'failed' ||
                processingStatus === 'cancelled') &&
                record.can_edit && (
                    <Button
                        size="sm"
                        variant="secondary"
                        className="h-7"
                        onClick={() => openRetry(null)}
                    >
                        <RotateCcw aria-hidden />
                        Retry
                    </Button>
                )}
            {record.can_edit && (
                <RetryProcessingDialog
                    recordId={record.id}
                    presets={presets}
                    initialOptions={record.pipeline_options}
                    open={retryOpen}
                    onOpenChange={setRetryOpen}
                    stage={retryStage}
                />
            )}
            {record.batch && (
                <Button
                    asChild
                    size="sm"
                    variant="ghost"
                    className="hidden h-7 lg:inline-flex"
                >
                    <Link
                        href={showBatch(record.batch.id)}
                        title={'Uploaded in ' + record.batch.name}
                    >
                        <Layers aria-hidden />
                        Batch
                    </Link>
                </Button>
            )}
            {record.accession_number && (
                <span className="hidden font-mono text-xs text-muted-foreground lg:inline">
                    {record.accession_number}
                </span>
            )}
            <span className="hidden md:inline-flex">
                <ClassificationBadge classification={record.classification} />
            </span>
            {record.embargo_until && (
                <span className="hidden md:inline-flex">
                    <EmbargoBadge until={record.embargo_until} />
                </span>
            )}
            <ReviewStatusDialog
                recordId={record.id}
                status={record.review_status}
                review={review}
            />
            {review.can_submit && (
                <SubmitRecordDialog
                    recordId={record.id}
                    ready={processingStatus === 'ready'}
                    pendingSuggestions={pendingSuggestions}
                />
            )}
            {!record.can_edit && (
                <span className="hidden text-xs text-muted-foreground md:inline">
                    Read-only
                </span>
            )}
            <ShortcutsDialog />
            <Button
                size="sm"
                variant={exportOpen ? 'secondary' : 'outline'}
                aria-pressed={exportOpen}
                onClick={() =>
                    setInspectorTab(
                        exportOpen
                            ? useWorkspaceStore.getState().previousTab
                            : 'export',
                    )
                }
            >
                <Download aria-hidden />
                <span className="hidden sm:inline">Export</span>
            </Button>
            <Button
                size="sm"
                variant={publishOpen ? 'secondary' : 'outline'}
                aria-pressed={publishOpen}
                onClick={() =>
                    setInspectorTab(
                        publishOpen
                            ? useWorkspaceStore.getState().previousTab
                            : 'publish',
                    )
                }
            >
                <Youtube aria-hidden />
                <span className="hidden sm:inline">Publish</span>
            </Button>
            {record.can_delete && (
                <DeleteRecordDialog
                    recordId={record.id}
                    title={record.title}
                    processing={working}
                />
            )}
        </HeaderActions>
    );
}
