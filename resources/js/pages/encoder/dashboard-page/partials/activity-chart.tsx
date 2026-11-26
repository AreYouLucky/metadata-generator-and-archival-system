import { useState } from 'react';
import { formatDate, formatNumber } from '@/lib/utilities';
import type { DashboardActivityDay } from '@/types/dashboard';
import DashboardCard from './dashboard-card';

/**
 * Records added per day over the last 30 days: one series, bars anchored to
 * the baseline. Hovering or focusing a day shows its count in the readout.
 */
export default function ActivityChart({
    days,
}: {
    days: DashboardActivityDay[];
}) {
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(...days.map((day) => day.count), 0);
    const total = days.reduce((sum, day) => sum + day.count, 0);
    const shown = active === null ? null : days[active];

    return (
        <DashboardCard
            title="Records added, last 30 days"
            meta={
                <span aria-live="polite">
                    {shown
                        ? `${formatDate(shown.date)} · ${formatNumber(shown.count)} added`
                        : `${formatNumber(total)} in total`}
                </span>
            }
        >
            {max === 0 ? (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    Nothing was added in the last 30 days.
                </p>
            ) : (
                <div
                    className="flex h-36 items-end gap-0.5 border-b border-border"
                    onMouseLeave={() => setActive(null)}
                >
                    {days.map((day, position) => (
                        <button
                            key={day.date}
                            type="button"
                            className="group flex h-full min-w-0 flex-1 items-end focus-visible:outline-none"
                            aria-label={`${formatDate(day.date)}: ${formatNumber(day.count)} added`}
                            onMouseEnter={() => setActive(position)}
                            onFocus={() => setActive(position)}
                            onBlur={() => setActive(null)}
                        >
                            <span
                                className={`block w-full rounded-t-[4px] transition-colors ${
                                    active === position
                                        ? 'bg-brand'
                                        : 'bg-brand/60 group-focus-visible:bg-brand'
                                }`}
                                style={{
                                    height: `${day.count > 0 ? Math.max(4, (day.count / max) * 100) : 0}%`,
                                }}
                            />
                        </button>
                    ))}
                </div>
            )}

            {days.length > 0 && (
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>{formatDate(days[0].date)}</span>
                    <span>Today</span>
                </div>
            )}
        </DashboardCard>
    );
}
