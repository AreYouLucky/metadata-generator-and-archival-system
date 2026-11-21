import { Link } from '@inertiajs/react';
import { Film } from 'lucide-react';
import ProcessingStatusBadge from '@/components/encoder/processing-status-badge';
import ReviewStatusBadge from '@/components/encoder/review-status-badge';
import { formatDate, formatTimestamp } from '@/lib/utilities';
import { show } from '@/routes/records';
import type { RecordListItem } from '@/types/records';

export default function RecordsTable({
    records,
    showCreator,
}: {
    records: RecordListItem[];
    showCreator: boolean;
}) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                    <tr>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Title
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Duration
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Processing
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Review
                        </th>
                        {showCreator && (
                            <th scope="col" className="px-4 py-3 font-medium">
                                Encoder
                            </th>
                        )}
                        <th scope="col" className="px-4 py-3 font-medium">
                            Added
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {records.map((record) => (
                        <tr key={record.id} className="hover:bg-muted/30">
                            <td className="max-w-md px-4 py-3">
                                <Link
                                    href={show(record.id)}
                                    className="flex items-center gap-3 font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand">
                                        <Film className="size-4" aria-hidden />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate">
                                            {record.title}
                                        </span>
                                        {record.project && (
                                            <span className="block truncate text-xs font-normal text-muted-foreground">
                                                {record.project}
                                                {record.series &&
                                                    ` › ${record.series}`}
                                            </span>
                                        )}
                                    </span>
                                </Link>
                            </td>
                            <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                {record.duration !== null
                                    ? formatTimestamp(record.duration)
                                    : '—'}
                            </td>
                            <td className="px-4 py-3">
                                <ProcessingStatusBadge
                                    status={record.processing_status}
                                />
                            </td>
                            <td className="px-4 py-3">
                                <ReviewStatusBadge
                                    status={record.review_status}
                                />
                            </td>
                            {showCreator && (
                                <td className="px-4 py-3 text-muted-foreground">
                                    {record.creator ?? '—'}
                                </td>
                            )}
                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                {formatDate(record.created_at)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
