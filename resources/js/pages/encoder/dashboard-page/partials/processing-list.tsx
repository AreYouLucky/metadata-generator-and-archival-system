import { Link } from '@inertiajs/react';
import ProcessingStatusBadge from '@/components/all/processing-status-badge';
import { formatTimeAgo } from '@/lib/utilities';
import { show } from '@/routes/records';
import type {
    DashboardProcessingItem,
    DashboardStats,
} from '@/types/dashboard';
import DashboardCard from './dashboard-card';

/**
 * Videos in the pipeline now, oldest first, with the running stage and an
 * overall progress bar. The page refreshes this while anything is listed.
 */
export default function ProcessingList({
    items,
    stats,
}: {
    items: DashboardProcessingItem[];
    stats: DashboardStats;
}) {
    return (
        <DashboardCard
            title="Processing now"
            meta={`${stats.processing.queued} queued · ${stats.processing.processing} running`}
        >
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nothing is processing.
                </p>
            ) : (
                <ul className="grid gap-3">
                    {items.map((item) => (
                        <li key={item.id} className="grid gap-1.5">
                            <div className="flex items-center justify-between gap-3">
                                <Link
                                    href={show(item.id)}
                                    className="line-clamp-1 min-w-0 text-sm font-medium hover:underline"
                                >
                                    {item.title}
                                </Link>
                                <ProcessingStatusBadge
                                    status={item.processing_status}
                                    className="shrink-0"
                                />
                            </div>
                            <div
                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={item.progress}
                                aria-label={`${item.title} progress`}
                            >
                                <div
                                    className="h-full rounded-full bg-brand transition-[width] duration-500"
                                    style={{ width: `${item.progress}%` }}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {item.stage ?? 'Waiting to start'} ·{' '}
                                {item.progress}%
                                {item.creator && ` · ${item.creator}`} · added{' '}
                                {formatTimeAgo(item.created_at)}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardCard>
    );
}
