import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/records';
import type { RecordFilters } from '@/types/records';

const ALL = 'all';

const PROCESSING_OPTIONS = [
    ['queued', 'Queued'],
    ['processing', 'Processing'],
    ['ready', 'Ready'],
    ['failed', 'Failed'],
] as const;

const REVIEW_OPTIONS = [
    ['draft', 'Draft'],
    ['submitted', 'Submitted'],
    ['changes_requested', 'Changes requested'],
    ['approved', 'Approved'],
    ['rejected', 'Rejected'],
] as const;

const FLAG_OPTIONS = [
    ['embargoed', 'Embargoed'],
    ['classified', 'Classified'],
] as const;

const SORT_OPTIONS = [
    ['newest', 'Newest first'],
    ['oldest', 'Oldest first'],
    ['title', 'Title A–Z'],
] as const;

/**
 * Search (debounced) and filters, kept in the query string so the list can be
 * bookmarked and the back button works.
 */
export default function RecordsFilters({
    filters,
}: {
    filters: RecordFilters;
}) {
    const [search, setSearch] = useState(filters.q);
    const firstRender = useRef(true);

    const apply = (changes: Partial<RecordFilters>) => {
        const next = { ...filters, q: search, ...changes };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([key, value]) =>
                        value !== null &&
                        value !== '' &&
                        !(key === 'sort' && value === 'newest'),
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => apply({ q: search }), 350);

        return () => clearTimeout(timer);
        // Only the search text triggers the debounced visit.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative w-full sm:w-72">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search titles or accession numbers"
                    aria-label="Search records by title or accession number"
                    className="pl-9"
                />
            </div>

            <Select
                value={filters.processing_status ?? ALL}
                onValueChange={(value) =>
                    apply({
                        processing_status:
                            value === ALL
                                ? null
                                : (value as RecordFilters['processing_status']),
                    })
                }
            >
                <SelectTrigger className="w-40" aria-label="Processing status">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Any processing</SelectItem>
                    {PROCESSING_OPTIONS.map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.review_status ?? ALL}
                onValueChange={(value) =>
                    apply({
                        review_status:
                            value === ALL
                                ? null
                                : (value as RecordFilters['review_status']),
                    })
                }
            >
                <SelectTrigger className="w-44" aria-label="Review status">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Any review status</SelectItem>
                    {REVIEW_OPTIONS.map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.flag ?? ALL}
                onValueChange={(value) =>
                    apply({
                        flag:
                            value === ALL
                                ? null
                                : (value as RecordFilters['flag']),
                    })
                }
            >
                <SelectTrigger className="w-40" aria-label="Archival flags">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Any access</SelectItem>
                    {FLAG_OPTIONS.map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.sort}
                onValueChange={(value) =>
                    apply({ sort: value as RecordFilters['sort'] })
                }
            >
                <SelectTrigger className="w-36" aria-label="Sort">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {SORT_OPTIONS.map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
