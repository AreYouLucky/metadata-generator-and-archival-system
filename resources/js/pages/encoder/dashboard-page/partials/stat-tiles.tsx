import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { CheckCircle2, Clock3, FileVideo, Hourglass } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatNumber, formatRunningTime } from '@/lib/utilities';
import { index as approvals } from '@/routes/admin/approvals';
import { index as records } from '@/routes/records';
import { index as reviews } from '@/routes/reviews';
import type { DashboardStats } from '@/types/dashboard';

type Tile = {
    label: string;
    value: string;
    hint: string;
    icon: LucideIcon;
    href: InertiaLinkProps['href'];
};

/**
 * The four headline numbers. Each tile opens the list behind it.
 */
export default function StatTiles({
    stats,
    isAdmin,
}: {
    stats: DashboardStats;
    isAdmin: boolean;
}) {
    const tiles: Tile[] = [
        {
            label: isAdmin ? 'Records in the archive' : 'Your records',
            value: formatNumber(stats.total),
            hint: `${formatNumber(stats.added_this_week)} added this week`,
            icon: FileVideo,
            href: records(),
        },
        {
            label: 'Footage archived',
            value: formatRunningTime(stats.duration_seconds),
            hint: 'Total running time of the masters',
            icon: Clock3,
            href: records(),
        },
        {
            label: isAdmin ? 'Waiting for your approval' : 'Waiting for review',
            value: formatNumber(stats.review.submitted),
            hint: isAdmin ? 'Open the approval queue' : 'Submitted to an admin',
            icon: Hourglass,
            href: isAdmin ? approvals() : reviews(),
        },
        {
            label: 'Approved',
            value: formatNumber(stats.review.approved),
            hint:
                stats.total > 0
                    ? `${Math.round((stats.review.approved / stats.total) * 100)}% of all records`
                    : 'Nothing approved yet',
            icon: CheckCircle2,
            href: records({ query: { review_status: 'approved' } }),
        },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {tiles.map((tile) => (
                <Link
                    key={tile.label}
                    href={tile.href}
                    className="group rounded-xl border bg-card p-4 transition-colors hover:border-brand/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span className="grid size-7 place-items-center rounded-md bg-secondary/15 text-brand">
                            <tile.icon className="size-4" aria-hidden />
                        </span>
                        {tile.label}
                    </p>
                    <p className="mt-2 text-2xl font-semibold tabular-nums">
                        {tile.value}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {tile.hint}
                    </p>
                </Link>
            ))}
        </div>
    );
}
