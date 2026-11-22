import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    /** The list page to reload, e.g. `index.url()`. */
    url: string;
    initial: string;
    label: string;
};

/**
 * Debounced title search for a list page, kept in the query string as `q`.
 */
export default function ListSearch({ url, initial, label }: Props) {
    const [search, setSearch] = useState(initial);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(
            () =>
                router.get(url, search ? { q: search } : {}, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            350,
        );

        return () => clearTimeout(timer);
    }, [search, url]);

    return (
        <div className="relative w-full sm:w-80">
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <Input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={label}
                aria-label={label}
                className="pl-9"
            />
        </div>
    );
}
