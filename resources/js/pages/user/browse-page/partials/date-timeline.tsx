import { cn } from '@/lib/utils';

type Props = {
    years: { year: number; count: number }[];
    months: { month: number; count: number }[];
    year: number | null;
    month: number | null;
    onChange: (year: number | null, month: number | null) => void;
};

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/**
 * Browse by date: a year, then a month of that year. Only years and months
 * with published videos are offered.
 */
export default function DateTimeline({
    years,
    months,
    year,
    month,
    onChange,
}: Props) {
    if (years.length === 0) {
        return null;
    }

    const chip = (active: boolean) =>
        cn(
            'shrink-0 rounded-full border px-3 py-1 text-sm transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            active
                ? 'border-brand bg-brand text-brand-foreground'
                : 'hover:border-brand/50 hover:bg-brand-muted',
        );

    return (
        <nav aria-label="Date" className="grid gap-2">
            <ul className="flex gap-2 overflow-x-auto pb-1">
                <li>
                    <button
                        type="button"
                        className={chip(year === null)}
                        onClick={() => onChange(null, null)}
                    >
                        All years
                    </button>
                </li>
                {years.map((item) => (
                    <li key={item.year}>
                        <button
                            type="button"
                            className={chip(year === item.year)}
                            aria-pressed={year === item.year}
                            onClick={() => onChange(item.year, null)}
                        >
                            {item.year}
                            <span className="ml-1 text-xs opacity-70">
                                {item.count}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            {year !== null && months.length > 0 && (
                <ul
                    className="flex gap-2 overflow-x-auto pb-1"
                    aria-label={`Months of ${year}`}
                >
                    <li>
                        <button
                            type="button"
                            className={chip(month === null)}
                            onClick={() => onChange(year, null)}
                        >
                            All of {year}
                        </button>
                    </li>
                    {months.map((item) => (
                        <li key={item.month}>
                            <button
                                type="button"
                                className={chip(month === item.month)}
                                aria-pressed={month === item.month}
                                onClick={() => onChange(year, item.month)}
                            >
                                {MONTHS[item.month - 1]}
                                <span className="ml-1 text-xs opacity-70">
                                    {item.count}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </nav>
    );
}
