import { router } from '@inertiajs/react';
import { ArrowRight, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { browse } from '@/routes/catalogue';

/**
 * The hero's large search box: submits to /browse?q=, where the results can
 * be filtered further.
 */
export default function HeroSearch() {
    const [query, setQuery] = useState('');

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(browse.url(), query.trim() ? { q: query.trim() } : {});
    };

    return (
        <form
            onSubmit={search}
            role="search"
            className="flex w-full max-w-2xl items-center gap-2 rounded-full border bg-background p-1.5 shadow-sm focus-within:ring-2 focus-within:ring-ring"
        >
            <Search
                className="ml-3 size-5 shrink-0 text-muted-foreground"
                aria-hidden
            />
            <Input
                type="search"
                value={query}
                maxLength={100}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search titles, people, places, keywords…"
                aria-label="Search the archive"
                className="h-10 border-0 bg-transparent px-1 text-base shadow-none focus-visible:ring-0 md:text-base"
            />
            <Button
                type="submit"
                className="group h-10 shrink-0 rounded-full bg-sky-600 px-5 hover:bg-sky-700"
            >
                Search
                <ArrowRight
                    className="transition-transform group-hover:translate-x-0.5"
                    aria-hidden
                />
            </Button>
        </form>
    );
}
