import { Link } from '@inertiajs/react';
import SimplePagination from '@/components/all/simple-pagination';
import { formatCost, formatNumber } from '@/lib/utilities';
import { show } from '@/routes/records';
import type { Paginated } from '@/types/pagination';
import type { RecordUsage } from '@/types/usage';

/**
 * Usage per record for the range, highest cost first, paginated.
 */
export default function UsageRecordsTable({
    records,
    encoderName,
}: {
    records: Paginated<RecordUsage>;
    encoderName: string | null;
}) {
    return (
        <section className="grid gap-3">
            <h2 className="text-sm font-medium">
                By record{encoderName ? ` (${encoderName})` : ''}
            </h2>
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Record
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Encoder
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Minutes
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Tokens (in / out)
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Est. cost
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y tabular-nums">
                        {records.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No usage in this period.
                                </td>
                            </tr>
                        ) : (
                            records.data.map((row) => (
                                <tr key={row.record_id}>
                                    <td className="max-w-80 px-4 py-2.5">
                                        {row.deleted ? (
                                            <span className="text-muted-foreground">
                                                {row.title ?? 'Deleted record'}{' '}
                                                (deleted)
                                            </span>
                                        ) : (
                                            <Link
                                                href={show(row.record_id)}
                                                className="line-clamp-1 hover:underline"
                                            >
                                                {row.title}
                                            </Link>
                                        )}
                                        {row.accession_number && (
                                            <span className="block font-mono text-xs text-muted-foreground">
                                                {row.accession_number}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        {row.encoder ?? '—'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {formatNumber(row.minutes, 1)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {formatNumber(row.input_tokens)} /{' '}
                                        {formatNumber(row.output_tokens)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {formatCost(row.cost)}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            <SimplePagination page={records} />
        </section>
    );
}
