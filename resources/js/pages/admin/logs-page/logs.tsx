import { Head, Link } from '@inertiajs/react';
import SimplePagination from '@/components/all/simple-pagination';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatDateTime, formatEnumLabel } from '@/lib/utilities';
import { index } from '@/routes/admin/logs';
import { show } from '@/routes/records';
import type { LogEntry, LogFilters } from '@/types/logs';
import type { Paginated } from '@/types/pagination';
import LogsFilters from './partials/logs-filters';

type Props = {
    logs: Paginated<LogEntry>;
    filters: LogFilters;
    actions: string[];
};

/**
 * Admin-only: what happened to each video (archive activity) and who viewed
 * or downloaded it, newest first. Both logs are append-only.
 */
export default function Logs({ logs, filters, actions }: Props) {
    const isAccess = filters.type === 'access';

    return (
        <>
            <Head title="Logs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Logs"
                    description="Archive activity (uploads, reviews, checksums, exports) and who viewed or downloaded each video. Entries are never edited or removed."
                />

                <LogsFilters filters={filters} actions={actions} />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    When
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Action
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Video
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    By
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    {isAccess ? 'IP address' : 'Notes'}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {logs.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No entries.
                                    </td>
                                </tr>
                            ) : (
                                logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(log.at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="secondary">
                                                {formatEnumLabel(log.action)}
                                            </Badge>
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3">
                                            {log.record.deleted ? (
                                                <span className="text-muted-foreground">
                                                    {log.record.title ??
                                                        'Purged video'}{' '}
                                                    (deleted)
                                                </span>
                                            ) : (
                                                <Link
                                                    href={show(log.record.id)}
                                                    className="hover:underline"
                                                >
                                                    {log.record.title}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.user ?? (
                                                <span className="text-muted-foreground">
                                                    {isAccess
                                                        ? 'Guest'
                                                        : 'System'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="max-w-sm px-4 py-3 text-muted-foreground">
                                            <span className="line-clamp-2">
                                                {log.details ?? '—'}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <SimplePagination page={logs} />
            </div>
        </>
    );
}

Logs.layout = {
    breadcrumbs: [{ title: 'Logs', href: index() }],
};
