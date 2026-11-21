import { Download } from 'lucide-react';
import HeaderActions from '@/components/all/header-actions';
import ReviewStatusBadge from '@/components/encoder/review-status-badge';
import { Button } from '@/components/ui/button';
import { exportMethod } from '@/routes/records';
import type {
    PresetName,
    PresetRules,
    ProcessingStatus,
    StageState,
    WorkspaceRecord,
} from '@/types/records';
import ProcessingStatusMenu from './processing-status-menu';
import RetryProcessingDialog from './retry-processing-dialog';

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
 * after a failure, review status and export.
 */
export default function WorkspaceHeaderActions({
    record,
    stages,
    processingStatus,
    presets,
    initialPreset,
}: Props) {
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
            <span className="hidden md:inline-flex">
                <ReviewStatusBadge status={record.review_status} />
            </span>
            {!record.can_edit && (
                <span className="hidden text-xs text-muted-foreground md:inline">
                    Read-only
                </span>
            )}
            <Button size="sm" variant="outline" asChild>
                {/* A plain link: the browser handles the download. */}
                <a
                    href={exportMethod.url(record.id, {
                        query: { format: 'json' },
                    })}
                    download
                >
                    <Download aria-hidden />
                    <span className="hidden sm:inline">Export JSON</span>
                </a>
            </Button>
        </HeaderActions>
    );
}
