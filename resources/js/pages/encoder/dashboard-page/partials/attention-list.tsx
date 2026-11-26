import { Link } from '@inertiajs/react';
import { AlertTriangle, MessageSquareWarning } from 'lucide-react';
import { formatTimeAgo } from '@/lib/utilities';
import { show } from '@/routes/records';
import type { DashboardAttentionItem } from '@/types/dashboard';
import DashboardCard from './dashboard-card';

const REASONS = {
    failed: {
        label: 'Processing failed',
        icon: AlertTriangle,
        tone: 'bg-red-100 text-red-700',
    },
    changes_requested: {
        label: 'Changes requested',
        icon: MessageSquareWarning,
        tone: 'bg-yellow-100 text-yellow-800',
    },
} as const;

/**
 * Records waiting on the encoder: a failed pipeline to retry, or an admin's
 * request for changes.
 */
export default function AttentionList({
    items,
}: {
    items: DashboardAttentionItem[];
}) {
    return (
        <DashboardCard title="Needs attention">
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    All clear. Nothing has failed and no changes were requested.
                </p>
            ) : (
                <ul className="divide-y">
                    {items.map((item) => {
                        const reason = REASONS[item.reason];

                        return (
                            <li
                                key={item.id}
                                className="flex items-start gap-3 py-2"
                            >
                                <span
                                    className={`mt-0.5 grid size-7 shrink-0 place-items-center rounded-md ${reason.tone}`}
                                >
                                    <reason.icon
                                        className="size-4"
                                        aria-hidden
                                    />
                                </span>
                                <div className="min-w-0">
                                    <Link
                                        href={show(item.id)}
                                        className="line-clamp-1 text-sm font-medium hover:underline"
                                    >
                                        {item.title}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {reason.label}
                                        {item.creator && ` · ${item.creator}`}
                                        {' · '}
                                        {formatTimeAgo(item.updated_at)}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </DashboardCard>
    );
}
