import { useState } from 'react';
import { formatCost, formatDate, formatNumber } from '@/lib/utilities';
import type { DailyUsage } from '@/types/usage';

/**
 * Transcription minutes per day: one series, one hue, bars anchored to the
 * baseline. Hovering (or focusing) a day shows its minutes and estimated cost
 * in the readout above; the tables below are the full table view.
 */
export default function UsageDailyChart({ days }: { days: DailyUsage[] }) {
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(...days.map((day) => day.minutes), 0);
    const shown = active === null ? null : days[active];

    return (
        <figure className="grid gap-3 rounded-xl border p-4">
            <figcaption className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="text-sm font-medium">
                    Transcription minutes per day
                </span>
                <span
                    className="text-xs text-muted-foreground tabular-nums"
                    aria-live="polite"
                >
                    {shown
                        ? `${formatDate(shown.date)} · ${formatNumber(shown.minutes, 1)} min · ${formatCost(shown.cost)}`
                        : `Peak ${formatNumber(max, 1)} min`}
                </span>
            </figcaption>

            {max === 0 ? (
                <p className="py-10 text-center text-sm text-muted-foreground">
                    No transcription in this period.
                </p>
            ) : (
                <div
                    className="flex h-40 items-end gap-0.5 border-b border-border"
                    onMouseLeave={() => setActive(null)}
                >
                    {days.map((day, position) => (
                        <button
                            key={day.date}
                            type="button"
                            className="group flex h-full min-w-0 flex-1 items-end focus-visible:outline-none"
                            aria-label={`${formatDate(day.date)}: ${formatNumber(day.minutes, 1)} minutes, ${formatCost(day.cost)}`}
                            onMouseEnter={() => setActive(position)}
                            onFocus={() => setActive(position)}
                            onBlur={() => setActive(null)}
                        >
                            <span
                                className={`block w-full rounded-t-[4px] transition-colors ${
                                    active === position
                                        ? 'bg-brand'
                                        : 'bg-brand/70 group-focus-visible:bg-brand'
                                }`}
                                style={{
                                    height: `${day.minutes > 0 ? Math.max(2, (day.minutes / max) * 100) : 0}%`,
                                }}
                            />
                        </button>
                    ))}
                </div>
            )}

            {days.length > 0 && (
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>{formatDate(days[0].date)}</span>
                    <span>{formatDate(days[days.length - 1].date)}</span>
                </div>
            )}
        </figure>
    );
}
