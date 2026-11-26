import { formatNumber } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { DashboardAllowance } from '@/types/dashboard';
import DashboardCard from './dashboard-card';

/**
 * Today's transcription minutes against the encoder's daily limit, so they
 * know before uploading whether a long video will be refused.
 */
export default function ProcessingAllowance({
    allowance,
}: {
    allowance: NonNullable<DashboardAllowance>;
}) {
    const percent =
        allowance.limit > 0
            ? Math.min(
                  100,
                  Math.round((allowance.used / allowance.limit) * 100),
              )
            : 100;

    return (
        <DashboardCard
            title="Today's processing allowance"
            meta={`${formatNumber(allowance.limit)} min a day`}
        >
            <p className="text-2xl font-semibold tabular-nums">
                {formatNumber(allowance.remaining, 1)}
                <span className="ml-1 text-sm font-normal text-muted-foreground">
                    min left
                </span>
            </p>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="meter"
                aria-valuemin={0}
                aria-valuemax={allowance.limit}
                aria-valuenow={allowance.used}
                aria-label="Transcription minutes used today"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        percent >= 90 ? 'bg-red-500' : 'bg-yellow-400',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
            <p className="text-xs text-muted-foreground tabular-nums">
                {formatNumber(allowance.used, 1)} of{' '}
                {formatNumber(allowance.limit)} transcription minutes used.
                Resets at midnight.
            </p>
        </DashboardCard>
    );
}
