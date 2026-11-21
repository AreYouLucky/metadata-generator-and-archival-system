import { Download } from 'lucide-react';
import HeaderActions from '@/components/all/header-actions';
import ClassificationBadge from '@/components/encoder/classification-badge';
import EmbargoBadge from '@/components/encoder/embargo-badge';
import ReviewStatusBadge from '@/components/encoder/review-status-badge';
import { Button } from '@/components/ui/button';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type {
    PresetName,
    PresetRules,
    ProcessingStatus,
    StageState,
    WorkspaceRecord,
} from '@/types/records';
import ProcessingStatusMenu from './processing-status-menu';
import RetryProcessingDialog from './retry-processing-dialog';
import ShortcutsDialog from './shortcuts-dialog';

type Props = {
    record: WorkspaceRecord;
    stages: StageState[];
    processingStatus: ProcessingStatus;
    presets: PresetRules[];
    initialPreset: PresetName;
};

/**
 * The workspace's controls in the app header, beside the breadcrumbs (which
 * already name the project, series and record): processing status, retry
 * after a failure, review status, keyboard shortcuts and Export (which opens
 * the inspector's Export tab).
 */
export default function WorkspaceHeaderActions({
    record,
    stages,
    processingStatus,
    presets,
    initialPreset,
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
            <span className="hidden md:inline-flex">
                <ReviewStatusBadge status={record.review_status} />
            </span>
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
