import { Head, Link } from '@inertiajs/react';
import SimplePagination from '@/components/all/simple-pagination';
import ListSearch from '@/components/encoder/list-search';
import ProcessingStatusBadge from '@/components/encoder/processing-status-badge';
import Heading from '@/components/heading';
import { formatDate } from '@/lib/utilities';
import { show } from '@/routes/records';
import { index } from '@/routes/reviews';
import type { Paginated } from '@/types/pagination';
import type { PendingReviewItem } from '@/types/reviews';

type Props = {
    records: Paginated<PendingReviewItem>;
    filters: { q: string };
    showCreator: boolean;
};

/**
 * Videos submitted for approval, oldest first. Encoders see their own; admins
 * see everyone's. Open one to check it in the workspace.
 */
export default function Reviews({ records, filters, showCreator }: Props) {
    return (
        <>
            <Head title="Pending reviews" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Pending reviews"
                    description={
                        showCreator
                            ? 'Videos submitted for approval, oldest first.'
                            : 'Your videos waiting for an admin to approve them, oldest first.'
                    }
                />

                <ListSearch
                    url={index.url()}
                    initial={filters.q}
                    label="Search titles"
                />

                {records.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">
                            {filters.q
                                ? 'No submitted videos match.'
                                : 'Nothing is waiting for review.'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Videos appear here once they are submitted for
                            approval.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {records.data.map((record) => (
                            <li
                                key={record.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                            >
                                <div className="min-w-0">
                                    <Link
                                        href={show(record.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {record.title}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {[
                                            record.project,
                                            record.series,
                                            showCreator && record.creator,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                    <ProcessingStatusBadge
                                        status={record.processing_status}
                                    />
                                    <span>
                                        Submitted{' '}
                                        {formatDate(record.submitted_at)}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={records} />
            </div>
        </>
    );
}

Reviews.layout = {
    breadcrumbs: [{ title: 'Pending reviews', href: index() }],
};
