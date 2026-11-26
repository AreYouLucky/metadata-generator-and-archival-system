import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

const QUERIES = [
    'Town hall meeting, March 2026',
    'Keynote speeches at the leadership forum',
    'Awards night program highlights',
    'Lola Remy’s 80th birthday',
];

/**
 * Decorative search bar that cycles through example queries.
 */
export default function SearchPreview() {
    const [queryIndex, setQueryIndex] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const timer = window.setInterval(
            () => setQueryIndex((index) => (index + 1) % QUERIES.length),
            3000,
        );

        return () => window.clearInterval(timer);
    }, []);

    return (
        <div
            aria-hidden
            className="flex h-14 items-center gap-3 rounded-2xl border bg-white px-4 shadow-lg shadow-brand/5"
        >
            <Search className="size-5 shrink-0 text-brand" />
            <span className="flex min-w-0 flex-1 items-center">
                <span
                    key={queryIndex}
                    className="animate-in truncate duration-500 fade-in slide-in-from-bottom-2"
                >
                    {QUERIES[queryIndex]}
                </span>
                <span className="ml-0.5 h-5 w-px shrink-0 animate-pulse bg-foreground" />
            </span>
            <kbd className="hidden rounded-md border bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground sm:block">
                Enter
            </kbd>
        </div>
    );
}
