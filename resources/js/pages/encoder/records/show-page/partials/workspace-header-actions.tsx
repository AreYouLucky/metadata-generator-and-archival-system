import { Download } from 'lucide-react';
import HeaderActions from '@/components/all/header-actions';
import ClassificationBadge from '@/components/all/classification-badge';
import EmbargoBadge from '@/components/encoder/embargo-badge';
import { Button } from '@/components/ui/button';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    PresetName,
    PresetRules,
    ProcessingStatus,
    StageState,
    WorkspaceRecord,
} from '@/types/records';
import type { WorkspaceReview } from '@/types/reviews';
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
    initialPreset: PresetName;
    review: WorkspaceReview;
    pendingSuggestions: number;
};

/**
 * The workspace's controls in the app header, beside the breadcrumbs (which
 * already name the project, series and record): processing status, retry
 * after a failure, review status (notes, history, admin actions), "Submit
 * for approval", keyboard shortcuts and Export (which opens the inspector's
 * Export tab).
 */
export default function WorkspaceHeaderActions({
    record,
    stages,
    processingStatus,
    presets,
    initialPreset,
    review,
    pendingSuggestions,
}: Props) {
    const setInspectorTab = useWorkspaceStore((state) => state.setInspectorTab);

    return (
        <HeaderActions>
            {stages.length > 0 && (
                <ProcessingStatusMenu
                    stages={stages}
                    processingStatus={processingStatus}
                />
            )}
            {processingStatus === 'failed' && record.can_edit && (
                <RetryProcessingDialog
                    recordId={record.id}
                    presets={presets}
                    initialPreset={initialPreset}
                />
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
                variant="outline"
                onClick={() => setInspectorTab('export')}
            >
                <Download aria-hidden />
                <span className="hidden sm:inline">Export</span>
            </Button>
        </HeaderActions>
    );
}
