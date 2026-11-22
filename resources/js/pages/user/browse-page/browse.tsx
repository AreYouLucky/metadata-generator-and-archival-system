import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SimplePagination from '@/components/all/simple-pagination';
import RecordGrid from '@/components/user/record-grid';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatNumber } from '@/lib/utilities';
import { browse } from '@/routes/catalogue';
import type {
    CatalogueFacets,
    CatalogueFilters,
    CataloguePage,
    CatalogueSeriesOption,
} from '@/types/catalogue';
import BrowseFilters from './partials/browse-filters';
import DateTimeline from './partials/date-timeline';

type Props = {
    records: CataloguePage;
    filters: CatalogueFilters;
    facets: CatalogueFacets;
    months: { month: number; count: number }[];
    seriesOptions: CatalogueSeriesOption[];
};

/**
 * Only the filters that are set go in the URL, so links stay short and
 * shareable.
 */
function toQuery(filters: CatalogueFilters): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(filters).filter(
            ([key, value]) =>
                value !== null &&
                value !== '' &&
                !(key === 'sort' && value === 'date_desc'),
        ),
    ) as Record<string, string | number>;
}

/**
 * Browse the public catalogue: by date (year → month), event, series, genre,
 * video type, agency and region, with search and sort. Each change is a new
 * URL, so results can be bookmarked and shared.
 */
export default function Browse({
    records,
    filters,
    facets,
    months,
    seriesOptions,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const firstRender = useRef(true);

    const visit = (next: Partial<CatalogueFilters>) =>
        router.get(browse.url(), toQuery({ ...filters, ...next }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    // Debounced search, like the staff lists.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(
            () =>
                router.get(
                    browse.url(),
                    toQuery({ ...filters, q: search.trim() || null }),
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            350,
        );

        return () => clearTimeout(timer);
        // Only the typed text triggers this; the other filters are read as they are.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const filtered = Object.keys(toQuery(filters)).some(
        (key) => key !== 'sort',
    );

    return (
        <>
            <Head title="Browse" />
            <div className="grid gap-6">
                <div className="grid gap-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Browse
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {formatNumber(records.total)}{' '}
                                {records.total === 1 ? 'video' : 'videos'}
                                {filtered ? ' match' : ''}
                            </p>
                        </div>
                        <div className="relative w-full max-w-sm">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                value={search}
                                maxLength={100}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Title, description, keyword…"
                                aria-label="Search the catalogue"
                                className="pl-8"
                            />
                        </div>
                    </div>
                    <DateTimeline
                        years={facets.years}
                        months={months}
                        year={filters.year}
                        month={filters.month}
                        onChange={(year, month) => visit({ year, month })}
                    />
                </div>

                <div className="grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
                    <aside className="grid content-start gap-4">
                        <BrowseFilters
                            filters={filters}
                            facets={facets}
                            seriesOptions={seriesOptions}
                            onChange={visit}
                        />
                        {filtered && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    router.get(browse.url());
                                }}
                            >
                                Clear filters
                            </Button>
                        )}
                    </aside>
                    <div className="grid content-start gap-6">
                        <RecordGrid
                            records={records.data}
                            empty={
                                filtered
                                    ? 'No videos match these filters.'
                                    : 'Nothing has been published yet.'
                            }
                        />
                        <SimplePagination page={records} />
                    </div>
                </div>
            </div>
        </>
    );
}
