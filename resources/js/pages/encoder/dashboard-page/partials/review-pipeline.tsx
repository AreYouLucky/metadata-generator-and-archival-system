import { Link } from '@inertiajs/react';
import { formatNumber } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { index as records } from '@/routes/records';
import type { DashboardStats } from '@/types/dashboard';
import type { ReviewStatus } from '@/types/records';
import DashboardCard from './dashboard-card';

/** In workflow order: draft → submitted → decision. */
const STEPS: { status: ReviewStatus; label: string; swatch: string }[] = [
    { status: 'draft', label: 'Draft', swatch: 'bg-slate-300' },
    { status: 'submitted', label: 'Submitted', swatch: 'bg-sky-500' },
    {
        status: 'changes_requested',
        label: 'Changes requested',
        swatch: 'bg-yellow-400',
    },
    { status: 'approved', label: 'Approved', swatch: 'bg-emerald-500' },
    { status: 'rejected', label: 'Rejected', swatch: 'bg-red-400' },
];

/**
 * Where records stand in the approval workflow: one proportional bar, and a
 * legend whose rows open the record list filtered to that status.
 */
export default function ReviewPipeline({ stats }: { stats: DashboardStats }) {
    const total = stats.total;

    return (
        <DashboardCard
            title="Review pipeline"
            meta={`${formatNumber(total)} records`}
        >
            <div
                className="flex h-3 w-full overflow-hidden rounded-full bg-muted"
                role="img"
                aria-label={STEPS.map(
                    (step) => `${step.label}: ${stats.review[step.status]}`,
                ).join(', ')}
            >
                {total > 0 &&
                    STEPS.map((step) =>
                        stats.review[step.status] > 0 ? (
                            <span
                                key={step.status}
                                className={cn('h-full', step.swatch)}
                                style={{
                                    width: `${(stats.review[step.status] / total) * 100}%`,
                                }}
                            />
                        ) : null,
                    )}
            </div>

            <ul className="grid gap-1 sm:grid-cols-2">
                {STEPS.map((step) => (
                    <li key={step.status}>
                        <Link
                            href={records({
                                query: { review_status: step.status },
                            })}
                            className="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                        >
                            <span className="flex items-center gap-2">
                                <span
                                    className={cn(
                                        'size-2.5 rounded-full',
                                        step.swatch,
                                    )}
                                    aria-hidden
                                />
                                {step.label}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {formatNumber(stats.review[step.status])}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </DashboardCard>
    );
}
