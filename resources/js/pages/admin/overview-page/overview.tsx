import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, Cpu, Globe, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import ProcessingStatusBadge from '@/components/all/processing-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    formatCost,
    formatDate,
    formatDateTime,
    formatNumber,
} from '@/lib/utilities';
import { overview } from '@/routes/admin';
import { index as approvals, show as review } from '@/routes/admin/approvals';
import { index as usage } from '@/routes/admin/usage';
import { home as catalogue } from '@/routes/catalogue';
import { show } from '@/routes/records';
import type { ProcessingStatus } from '@/types/records';
import type { EncoderUsage, UsageNumbers } from '@/types/usage';

type Props = {
    pending: {
        count: number;
        oldest: {
            id: string;
            title: string;
            creator: string | null;
            submitted_at: string | null;
        }[];
    };
    processing: {
        queued: number;
        running: number;
        failed: number;
        active: {
            id: string;
            title: string;
            creator: string | null;
            processing_status: ProcessingStatus;
            created_at: string | null;
        }[];
    };
    published: number;
    usage: {
        from: string;
        to: string;
        totals: UsageNumbers;
        top_encoders: EncoderUsage[];
    };
};

/**
 * Admin overview: what needs a decision, what is processing, and AI usage
 * this month.
 */
export default function Overview({
    pending,
    processing,
    published,
    usage: month,
}: Props) {
    const tiles: { label: string; value: string; icon: LucideIcon }[] = [
        {
            label: 'Waiting for approval',
            value: formatNumber(pending.count),
            icon: ClipboardCheck,
        },
        {
            label: 'Processing now',
            value: formatNumber(processing.queued + processing.running),
            icon: Cpu,
        },
        {
            label: 'On the public catalogue',
            value: formatNumber(published),
            icon: Globe,
        },
        {
            label: 'Estimated cost this month',
            value: formatCost(month.totals.cost),
            icon: Wallet,
        },
    ];

    return (
        <>
            <Head title="Overview" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Overview"
                    description="Approvals, processing and usage across the archive."
                />

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <div key={tile.label} className="rounded-xl border p-4">
                            <dt className="flex items-center gap-2 text-xs text-muted-foreground">
                                <tile.icon className="size-4" aria-hidden />
                                {tile.label}
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold tabular-nums">
                                {tile.value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="grid content-start gap-3 rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-medium">
                                Oldest waiting for approval
                            </h2>
                            <Button size="sm" variant="ghost" asChild>
                                <Link href={approvals()}>All approvals</Link>
                            </Button>
                        </div>
                        {pending.oldest.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing is waiting.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {pending.oldest.map((record) => (
                                    <li key={record.id} className="py-2">
                                        <Link
                                            href={review(record.id)}
                                            className="text-sm font-medium hover:underline"
                                        >
                                            {record.title}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {record.creator} · submitted{' '}
                                            {formatDateTime(
                                                record.submitted_at,
                                            )}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="grid content-start gap-3 rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-medium">
                                Processing queue
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {processing.queued} queued ·{' '}
                                {processing.running} running ·{' '}
                                {processing.failed} failed
                            </span>
                        </div>
                        {processing.active.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing is processing.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {processing.active.map((record) => (
                                    <li
                                        key={record.id}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        <div className="min-w-0">
                                            <Link
                                                href={show(record.id)}
                                                className="line-clamp-1 text-sm font-medium hover:underline"
                                            >
                                                {record.title}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {record.creator} · added{' '}
                                                {formatDateTime(
                                                    record.created_at,
                                                )}
                                            </p>
                                        </div>
                                        <ProcessingStatusBadge
                                            status={record.processing_status}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="grid content-start gap-3 rounded-xl border p-4 lg:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-sm font-medium">
                                Usage {formatDate(month.from)} –{' '}
                                {formatDate(month.to)}
                            </h2>
                            <div className="flex gap-1">
                                <Button size="sm" variant="ghost" asChild>
                                    <Link href={usage()}>Usage details</Link>
                                </Button>
                                <Button size="sm" variant="ghost" asChild>
                                    <a href={catalogue.url()}>
                                        Public catalogue
                                    </a>
                                </Button>
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {formatNumber(month.totals.minutes, 1)}{' '}
                            transcription minutes ·{' '}
                            {formatNumber(
                                month.totals.input_tokens +
                                    month.totals.output_tokens,
                            )}{' '}
                            LLM tokens · {formatCost(month.totals.cost)}{' '}
                            estimated
                        </p>
                        {month.top_encoders.length > 0 && (
                            <ul className="divide-y text-sm">
                                {month.top_encoders.map((row) => (
                                    <li
                                        key={row.user_id ?? 'deleted'}
                                        className="flex justify-between gap-3 py-2 tabular-nums"
                                    >
                                        <span>{row.name}</span>
                                        <span className="text-muted-foreground">
                                            {formatNumber(row.minutes, 1)} min ·{' '}
                                            {formatCost(row.cost)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

Overview.layout = {
    breadcrumbs: [{ title: 'Overview', href: overview() }],
};
