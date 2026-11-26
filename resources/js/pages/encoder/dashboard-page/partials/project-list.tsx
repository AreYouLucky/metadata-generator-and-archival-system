import { Link } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import { formatNumber, formatTimeAgo } from '@/lib/utilities';
import { index as projects, show } from '@/routes/projects';
import type { DashboardProjectItem } from '@/types/dashboard';
import DashboardCard from './dashboard-card';

/**
 * Projects with records in scope, the most recently added to first.
 */
export default function ProjectList({
    items,
    isAdmin,
}: {
    items: DashboardProjectItem[];
    isAdmin: boolean;
}) {
    return (
        <DashboardCard
            title={isAdmin ? 'Active projects' : 'Your projects'}
            action={{ label: 'All projects', href: projects() }}
        >
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No videos filed under a project yet.
                </p>
            ) : (
                <ul className="divide-y">
                    {items.map((item) => (
                        <li key={item.id}>
                            <Link
                                href={show(item.id)}
                                className="flex items-center gap-3 py-2 hover:underline"
                            >
                                <span className="grid size-7 shrink-0 place-items-center rounded-md bg-tertiary/25 text-yellow-800">
                                    <FolderKanban
                                        className="size-4"
                                        aria-hidden
                                    />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="line-clamp-1 text-sm font-medium">
                                        {item.title}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        Last video{' '}
                                        {formatTimeAgo(item.last_added_at)}
                                    </span>
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {formatNumber(item.records_count)}{' '}
                                    {item.records_count === 1
                                        ? 'video'
                                        : 'videos'}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardCard>
    );
}
