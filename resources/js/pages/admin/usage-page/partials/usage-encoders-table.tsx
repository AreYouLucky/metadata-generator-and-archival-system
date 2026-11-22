import { Link } from '@inertiajs/react';
import { formatCost, formatNumber } from '@/lib/utilities';
import { index } from '@/routes/admin/usage';
import type { EncoderUsage, UsageFilters } from '@/types/usage';

type Props = {
    encoders: EncoderUsage[];
    filters: UsageFilters;
};

/**
 * Usage per encoder for the range, highest cost first. A name narrows the
 * per-record table to that encoder.
 */
export default function UsageEncodersTable({ encoders, filters }: Props) {
    return (
        <section className="grid gap-3">
            <h2 className="text-sm font-medium">By encoder</h2>
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                        <tr>
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
                                Input tokens
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Output tokens
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
                        {encoders.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No usage in this period.
                                </td>
                            </tr>
                        ) : (
                            encoders.map((row) => (
                                <tr key={row.user_id ?? 'deleted'}>
                                    <td className="px-4 py-2.5">
                                        {row.user_id === null ? (
                                            row.name
                                        ) : (
                                            <Link
                                                href={index({
                                                    query: {
                                                        from: filters.from,
                                                        to: filters.to,
                                                        encoder: row.user_id,
                                                    },
                                                })}
                                                preserveScroll
                                                className={`hover:underline ${filters.encoder === row.user_id ? 'font-semibold' : ''}`}
                                            >
                                                {row.name}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {formatNumber(row.minutes, 1)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {formatNumber(row.input_tokens)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
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
        </section>
    );
}
