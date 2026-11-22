import { formatDateTime, formatEnumLabel } from '@/lib/utilities';
import type { ReviewHistoryItem } from '@/types/reviews';

const TONES: Record<ReviewHistoryItem['action'], string> = {
    submitted: 'bg-sky-500',
    approved: 'bg-emerald-500',
    changes_requested: 'bg-amber-500',
    rejected: 'bg-rose-500',
    reopened: 'bg-neutral-400',
};

/**
 * A record's review steps with their notes, newest first (workspace and the
 * admin review page).
 */
export default function ReviewHistory({
    history,
}: {
    history: ReviewHistoryItem[];
}) {
    if (history.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Not submitted for review yet.
            </p>
        );
    }

    return (
        <ol className="grid gap-3">
            {history.map((item) => (
                <li key={item.id} className="flex gap-3">
                    <span
                        aria-hidden
                        className={`mt-1.5 size-2 shrink-0 rounded-full ${TONES[item.action]}`}
                    />
                    <div className="min-w-0 flex-1">
                        <p className="text-sm">
                            <span className="font-medium">
                                {formatEnumLabel(item.action)}
                            </span>
                            {item.by && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    by {item.by}
                                </span>
                            )}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatDateTime(item.at)}
                        </p>
                        {item.notes && (
                            <p className="mt-1 rounded-md bg-muted px-3 py-2 text-sm whitespace-pre-line">
                                {item.notes}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
