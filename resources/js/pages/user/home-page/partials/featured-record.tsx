import { Link } from '@inertiajs/react';
import { Film, Play } from 'lucide-react';
import { formatDate, formatTimestamp } from '@/lib/utilities';
import { watch } from '@/routes/catalogue';
import type { CatalogueCard } from '@/types/catalogue';

/**
 * The newest published video, shown large in the hero with a play cue.
 */
export default function FeaturedRecord({ record }: { record: CatalogueCard }) {
    const meta = [
        record.date ? formatDate(record.date) : null,
        record.project,
        record.genre,
    ].filter(Boolean);

    return (
        <Link
            href={watch(record.id)}
            className="group relative block aspect-video overflow-hidden rounded-2xl border bg-muted shadow-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {record.thumbnail ? (
                <img
                    src={record.thumbnail}
                    alt=""
                    className="size-full object-cover transition duration-500 group-hover:scale-105"
                />
            ) : (
                <div className="flex size-full items-center justify-center bg-gradient-to-br from-sky-100 to-brand-muted">
                    <Film className="size-12 text-brand/60" aria-hidden />
                </div>
            )}

            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />

            <span className="absolute top-3 left-3 rounded-full bg-tertiary px-2.5 py-1 text-xs font-semibold text-tertiary-foreground">
                Newest
            </span>

            <span
                aria-hidden
                className="absolute top-1/2 left-1/2 flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-black shadow-lg transition group-hover:scale-110"
            >
                <Play className="ml-0.5 size-6 fill-current" />
            </span>

            <div className="absolute inset-x-0 bottom-0 grid gap-1 p-4 text-white sm:p-5">
                <h2 className="line-clamp-2 text-lg font-semibold text-balance sm:text-xl">
                    {record.title}
                </h2>
                <p className="flex flex-wrap items-center gap-x-2 text-xs text-white/75 sm:text-sm">
                    {meta.join(' · ')}
                    {record.duration !== null && (
                        <span className="rounded bg-black/60 px-1.5 py-0.5 font-medium text-white tabular-nums">
                            {formatTimestamp(record.duration)}
                        </span>
                    )}
                </p>
            </div>
        </Link>
    );
}
