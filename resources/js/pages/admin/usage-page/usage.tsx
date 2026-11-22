import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { formatCost, formatNumber } from '@/lib/utilities';
import { index } from '@/routes/admin/usage';
import type { Paginated } from '@/types/pagination';
import type {
    DailyUsage,
    EncoderUsage,
    RecordUsage,
    UsageFilters as Filters,
    UsageNumbers,
} from '@/types/usage';
import UsageDailyChart from './partials/usage-daily-chart';
import UsageEncodersTable from './partials/usage-encoders-table';
import UsageFilters from './partials/usage-filters';
import UsageRecordsTable from './partials/usage-records-table';

type Props = {
    filters: Filters;
    totals: UsageNumbers;
    daily: DailyUsage[];
    encoders: EncoderUsage[];
    records: Paginated<RecordUsage>;
    encoderName: string | null;
};

/**
 * Admin Usage: transcription minutes, LLM tokens and estimated cost for a
 * date range, per encoder and per record, with a CSV download.
 */
export default function Usage({
    filters,
    totals,
    daily,
    encoders,
    records,
    encoderName,
}: Props) {
    const tiles: [string, string][] = [
        ['Transcription minutes', formatNumber(totals.minutes, 1)],
        ['Input tokens', formatNumber(totals.input_tokens)],
        ['Output tokens', formatNumber(totals.output_tokens)],
        ['Estimated cost', formatCost(totals.cost)],
    ];

    return (
        <>
            <Head title="Usage" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Usage"
                    description="AI usage per encoder and per record. Costs are estimates from the providers' list prices."
                />

                <UsageFilters filters={filters} encoderName={encoderName} />

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map(([label, value]) => (
                        <div key={label} className="rounded-xl border p-4">
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <UsageDailyChart days={daily} />
                <UsageEncodersTable encoders={encoders} filters={filters} />
                <UsageRecordsTable
                    records={records}
                    encoderName={encoderName}
                />
            </div>
        </>
    );
}

Usage.layout = {
    breadcrumbs: [{ title: 'Usage', href: index() }],
};
