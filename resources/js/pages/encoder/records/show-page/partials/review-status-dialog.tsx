import { Link } from '@inertiajs/react';
import { ClipboardCheck, Eye, Globe } from 'lucide-react';
import ReopenRecordButton from '@/components/admin/reopen-record-button';
import ReviewHistory from '@/components/all/review-history';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatEnumLabel } from '@/lib/utilities';
import { show as reviewPage } from '@/routes/admin/approvals';
import { accessLog } from '@/routes/admin/records';
import { watch } from '@/routes/catalogue';
import type { ReviewStatus } from '@/types/records';
import type { WorkspaceReview } from '@/types/reviews';

type Props = {
    recordId: string;
    status: ReviewStatus;
    review: WorkspaceReview;
};

const TONES: Partial<Record<ReviewStatus, string>> = {
    approved: 'border-emerald-500/50 text-emerald-200',
    changes_requested: 'border-amber-500/60 text-amber-200',
    rejected: 'border-rose-500/60 text-rose-200',
    submitted: 'border-sky-500/50 text-sky-200',
};

/**
 * The review status in the header. Opens the review notes and history, and
 * for admins the review page, "Reopen" and the access log.
 */
export default function ReviewStatusDialog({
    recordId,
    status,
    review,
}: Props) {
    const latestNotes = review.history.find(
        (item) => item.action !== 'submitted' && item.notes,
    );
    const needsAttention = status === 'changes_requested';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    className={`h-7 gap-1.5 ${TONES[status] ?? ''}`}
                    title="Review status and notes"
                >
                    {review.is_public && (
                        <Globe className="size-3.5" aria-label="Public" />
                    )}
                    {formatEnumLabel(status)}
                    {needsAttention && latestNotes && (
                        <span
                            className="size-1.5 rounded-full bg-amber-400"
                            aria-hidden
                        />
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Review</DialogTitle>
                    <DialogDescription>
                        {review.is_public
                            ? 'Approved and on the public catalogue.'
                            : status === 'approved'
                              ? 'Approved, but its classification or rights keep it off the public catalogue.'
                              : `Status: ${formatEnumLabel(status).toLowerCase()}.`}
                    </DialogDescription>
                </DialogHeader>

                {needsAttention && latestNotes?.notes && (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        <p className="font-medium">Changes requested</p>
                        <p className="mt-1 whitespace-pre-line">
                            {latestNotes.notes}
                        </p>
                    </div>
                )}

                <ReviewHistory history={review.history} />

                {(review.can_review ||
                    review.can_reopen ||
                    review.can_view_access_log ||
                    review.is_public) && (
                    <div className="flex flex-wrap gap-2 border-t pt-4">
                        {review.can_review && (
                            <Button size="sm" asChild>
                                <Link href={reviewPage(recordId)}>
                                    <ClipboardCheck aria-hidden />
                                    Review
                                </Link>
                            </Button>
                        )}
                        {review.can_reopen && (
                            <ReopenRecordButton
                                recordId={recordId}
                                reloadProps={['record', 'review']}
                            />
                        )}
                        {review.is_public && (
                            <Button size="sm" variant="outline" asChild>
                                <a
                                    href={watch.url(recordId)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Globe aria-hidden />
                                    Public page
                                </a>
                            </Button>
                        )}
                        {review.can_view_access_log && (
                            <Button size="sm" variant="ghost" asChild>
                                <Link href={accessLog(recordId)}>
                                    <Eye aria-hidden />
                                    Access log
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
