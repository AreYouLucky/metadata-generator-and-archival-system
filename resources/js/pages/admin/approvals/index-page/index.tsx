import { Head, Link } from '@inertiajs/react';
import ListSearch from '@/components/all/list-search';
import SimplePagination from '@/components/all/simple-pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatTimestamp } from '@/lib/utilities';
import { index, show } from '@/routes/admin/approvals';
import type { Paginated } from '@/types/pagination';
import type { ApprovalQueueItem } from '@/types/reviews';

type Props = {
    records: Paginated<ApprovalQueueItem>;
    filters: { q: string };
};

/**
 * The admin review queue: every submitted record, oldest first. Open one to
 * watch it and approve, request changes or reject.
 */
export default function ApprovalsIndex({ records, filters }: Props) {
    return (
        <>
            <Head title="Approvals" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Approvals"
                    description="Records submitted for approval, oldest first. Approved records go to the public catalogue when their classification and rights allow."
                />

                <ListSearch
                    url={index.url()}
                    initial={filters.q}
                    label="Search titles or accession numbers"
                />

                {records.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">
                            {filters.q
                                ? 'No submitted records match.'
                                : 'Nothing is waiting for review.'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Records appear here when an encoder submits them.
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
                                            record.accession_number,
                                            record.project,
                                            record.series,
                                            record.creator,
                                            record.duration !== null &&
                                                formatTimestamp(
                                                    record.duration,
                                                ),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                    <span>
                                        Submitted{' '}
                                        {formatDateTime(record.submitted_at)}
                                    </span>
                                    <Button size="sm" asChild>
                                        <Link href={show(record.id)}>
                                            Review
                                        </Link>
                                    </Button>
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

ApprovalsIndex.layout = {
    breadcrumbs: [{ title: 'Approvals', href: index() }],
};
