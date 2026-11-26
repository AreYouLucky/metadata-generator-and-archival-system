import { Link } from '@inertiajs/react';
import { browse } from '@/routes/catalogue';
import type { CatalogueFacets } from '@/types/catalogue';

/**
 * Genres with their record counts, each a shortcut into a filtered Browse.
 */
export default function GenreChips({
    genres,
}: {
    genres: CatalogueFacets['genres'];
}) {
    return (
        <ul className="flex flex-wrap gap-2" aria-label="Genres">
            {genres.map((genre) => (
                <li key={genre.slug}>
                    <Link
                        href={browse({ query: { genre: genre.slug } })}
                        className="inline-flex items-center gap-1.5 rounded-full border bg-background/80 px-3 py-1 text-sm transition hover:border-sky-300 hover:bg-sky-50"
                    >
                        {genre.name}
                        <span className="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums">
                            {genre.count}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
