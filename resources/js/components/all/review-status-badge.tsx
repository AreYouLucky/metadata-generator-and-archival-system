import { Badge } from '@/components/ui/badge';
import type { ReviewStatus } from '@/types/records';

const LABELS: Record<ReviewStatus, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    approved: 'Approved',
    rejected: 'Rejected',
    changes_requested: 'Changes requested',
};

export default function ReviewStatusBadge({
    status,
}: {
    status: ReviewStatus;
}) {
    return <Badge variant="outline">{LABELS[status]}</Badge>;
}
