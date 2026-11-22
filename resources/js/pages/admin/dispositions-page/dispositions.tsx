import { Head, Link, router } from '@inertiajs/react';
import SimplePagination from '@/components/all/simple-pagination';
import ClassificationBadge from '@/components/all/classification-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatEnumLabel } from '@/lib/utilities';
import { index } from '@/routes/admin/dispositions';
import { show } from '@/routes/records';
import type { Option } from '@/types/archive';
import type { Paginated } from '@/types/pagination';

type DispositionItem = {
    id: string;
    title: string;
    accession_number: string | null;
    classification: string;
    retention_period: string | null;
    disposition_date: string;
    disposition_action: string | null;
    creator: string | null;
};

type Props = {
    records: Paginated<DispositionItem>;
    filters: { action: string | null };
    actions: Option[];
};

const ALL = 'all';

/**
 * Admin-only disposition review (PROJECT_SPEC §11): records whose disposition
 * date has passed, oldest first. Nothing is destroyed automatically; open a
 * record to retain, transfer or set a new date.
 */
export default function Dispositions({ records, filters, actions }: Props) {
    return (
        <>
            <Head title="Disposition review" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Disposition review"
                        description="Records whose retention period has ended. Nothing happens to them automatically: review each one and record the decision in its Archival tab."
                    />
                    <Select
                        value={filters.action ?? ALL}
                        onValueChange={(value) =>
                            router.get(
                                index.url(),
                                value === ALL ? {} : { action: value },
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Planned action"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Any action</SelectItem>
                            {actions.map((action) => (
                                <SelectItem
                                    key={action.value}
                                    value={action.value}
                                >
                                    {action.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Due
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Record
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Retention
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Planned action
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Encoder
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {records.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Nothing is due for review.
                                    </td>
                                </tr>
                            ) : (
                                records.data.map((record) => (
                                    <tr key={record.id}>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {formatDate(
                                                record.disposition_date,
                                            )}
                                        </td>
                                        <td className="max-w-sm px-4 py-3">
                                            <Link
                                                href={show(record.id)}
                                                className="block truncate font-medium hover:underline"
                                            >
                                                {record.title}
                                            </Link>
                                            <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                {record.accession_number && (
                                                    <span className="font-mono">
                                                        {
                                                            record.accession_number
                                                        }
                                                    </span>
                                                )}
                                                <ClassificationBadge
                                                    classification={
                                                        record.classification
                                                    }
                                                />
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {record.retention_period ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {record.disposition_action ? (
                                                <Badge variant="secondary">
                                                    {formatEnumLabel(
                                                        record.disposition_action,
                                                    )}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {record.creator ?? '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <SimplePagination page={records} />
            </div>
        </>
    );
}

Dispositions.layout = {
    breadcrumbs: [{ title: 'Disposition review', href: index() }],
};
