import { Link } from '@inertiajs/react';
import { Globe, PackageX } from 'lucide-react';
import ProcessingStatusBadge from '@/components/all/processing-status-badge';
import ReviewStatusBadge from '@/components/all/review-status-badge';
import { show } from '@/routes/records';
import type { BatchRecord } from '@/types/batches';

type Props = {
    record: BatchRecord;
};

/**
 * One record of the batch: its status, the step it is on (live), why it
 * failed, and whether the ZIP will leave it out.
 */
export default function BatchRecordRow({ record }: Props) {
    const current =
        record.stages.find((stage) => stage.status === 'running') ??
        record.stages.find((stage) => stage.status === 'pending');
    const finished = record.stages.filter((stage) =>
        ['done', 'skipped', 'failed', 'cancelled'].includes(stage.status),
    ).length;
    const overall = record.stages.length
        ? Math.round(
              ((finished +
                  (current?.status === 'running'
                      ? current.progress / 100
                      : 0)) /
                  record.stages.length) *
                  100,
          )
        : 0;
    const working =
        record.processing_status === 'queued' ||
        record.processing_status === 'processing';

    return (
        <li className="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
            <div className="min-w-0">
                <Link
                    href={show(record.id)}
                    className="flex items-center gap-1.5 font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                >
                    {record.source_host && (
                        <Globe
                            className="size-3.5 shrink-0 text-muted-foreground"
                            aria-label={'Imported from ' + record.source_host}
                        />
                    )}
                    <span className="truncate">{record.title}</span>
                </Link>
                <p className="text-xs text-muted-foreground">
                    {[record.accession_number, record.source_host]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {working && (
                    <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                        <span
                            className="h-1.5 w-40 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={overall}
                            aria-label={'Processing ' + record.title}
                        >
                            <span
                                className="block h-full bg-brand transition-[width]"
                                style={{ width: overall + '%' }}
                            />
                        </span>
                        <span className="tabular-nums">{overall}%</span>
                        {current && <span>· {current.label}</span>}
                    </div>
                )}
                {record.processing_error && (
                    <p className="mt-1 text-xs wrap-break-word text-red-600 dark:text-red-400">
                        {record.processing_error}
                    </p>
                )}
                {!working && record.zip_exclusions.length > 0 && (
                    <p className="mt-1 flex items-start gap-1 text-xs text-amber-700 dark:text-amber-400">
                        <PackageX
                            className="mt-px size-3.5 shrink-0"
                            aria-hidden
                        />
                        Left out of the ZIP: {record.zip_exclusions.join(' ')}
                    </p>
                )}
            </div>
            <div className="flex items-center gap-2">
                <ProcessingStatusBadge status={record.processing_status} />
                <ReviewStatusBadge status={record.review_status} />
            </div>
        </li>
    );
}
