import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useReviewRecord } from '@/hooks/use-review-record';
import { getErrorMessage } from '@/lib/utilities';
import { index } from '@/routes/admin/approvals';
import type { ReviewDecision } from '@/types/reviews';

type Props = {
    recordId: string;
};

const LABELS: Record<ReviewDecision, string> = {
    approve: 'Approve',
    request_changes: 'Request changes',
    reject: 'Reject',
};

/**
 * Approve, request changes or reject, with notes. Notes are required unless
 * approving, so the encoder knows what to fix. Back to the queue afterwards.
 */
export default function ReviewDecisionForm({ recordId }: Props) {
    const [notes, setNotes] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState<ReviewDecision | null>(null);
    const review = useReviewRecord(recordId);

    const decide = (action: ReviewDecision) => {
        if (action !== 'approve' && notes.trim() === '') {
            setError('Add a note so the encoder knows what to change.');

            return;
        }

        setError(null);
        setPending(action);
        review.mutate(
            { action, notes },
            {
                onSuccess: (data) => {
                    toast.success(data.message ?? 'Saved.');
                    router.visit(index());
                },
                onError: (failure) => toast.error(getErrorMessage(failure)),
                onSettled: () => setPending(null),
            },
        );
    };

    return (
        <div className="grid gap-3">
            <div className="grid gap-2">
                <Label htmlFor="review-notes">Notes for the encoder</Label>
                <Textarea
                    id="review-notes"
                    rows={4}
                    maxLength={2000}
                    value={notes}
                    onChange={(event) => setNotes(event.target.value)}
                    placeholder="Required when requesting changes or rejecting"
                />
                <InputError message={error ?? undefined} />
            </div>
            <div className="flex flex-wrap gap-2">
                {(Object.keys(LABELS) as ReviewDecision[]).map((action) => (
                    <Button
                        key={action}
                        variant={
                            action === 'approve'
                                ? 'default'
                                : action === 'reject'
                                  ? 'destructive'
                                  : 'outline'
                        }
                        disabled={review.isPending}
                        onClick={() => decide(action)}
                    >
                        {pending === action && <Spinner />}
                        {LABELS[action]}
                    </Button>
                ))}
            </div>
        </div>
    );
}
