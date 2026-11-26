import { Link } from '@inertiajs/react';
import { formatTimeAgo } from '@/lib/utilities';
import { index as records, show } from '@/routes/records';
import type { DashboardDraftItem } from '@/types/dashboard';
import DashboardCard from './dashboard-card';

/**
 * Processed drafts that still need checking and submitting, most recently
 * touched first: where to pick up the work.
 */
export default function DraftList({ items }: { items: DashboardDraftItem[] }) {
    return (
        <DashboardCard
            title="Ready for your review"
            action={{
                label: 'All drafts',
                href: records({ query: { review_status: 'draft' } }),
            }}
        >
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No processed drafts. New uploads land here once their
                    metadata is ready.
                </p>
            ) : (
                <ul className="divide-y">
                    {items.map((item) => (
                        <li key={item.id} className="py-2">
                            <Link
                                href={show(item.id)}
                                className="line-clamp-1 text-sm font-medium hover:underline"
                            >
                                {item.title}
                            </Link>
                            <p className="line-clamp-1 text-xs text-muted-foreground">
                                {[
                                    item.project,
                                    item.creator,
                                    `edited ${formatTimeAgo(item.updated_at)}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardCard>
    );
}
