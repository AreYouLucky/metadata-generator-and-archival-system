import { Link } from '@inertiajs/react';
import { Film } from 'lucide-react';
import { formatDate, formatTimestamp } from '@/lib/utilities';
import { watch } from '@/routes/catalogue';
import type { CatalogueCard } from '@/types/catalogue';

/**
 * A public record in a catalogue grid: cover (the project's), title, date,
 * event and labels. Links to the watch page.
 */
export default function RecordCard({ record }: { record: CatalogueCard }) {
    const labels = [record.genre, record.video_type, record.agency].filter(
        Boolean,
    );

    return (
        <Link
            href={watch(record.id)}
            className="group grid content-start gap-3 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="relative aspect-video overflow-hidden rounded-xl border bg-muted">
                {record.thumbnail ? (
                    <img
                        src={record.thumbnail}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover transition duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand-muted to-muted">
                        <Film className="size-8 text-brand/60" aria-hidden />
                    </div>
                )}
                {record.duration !== null && (
                    <span className="absolute right-2 bottom-2 rounded bg-black/75 px-1.5 py-0.5 text-xs font-medium text-white tabular-nums">
                        {formatTimestamp(record.duration)}
                    </span>
                )}
            </div>
            <div className="grid gap-1">
                <h3 className="line-clamp-2 font-medium group-hover:underline">
                    {record.title}
                </h3>
                <p className="line-clamp-1 text-xs text-muted-foreground">
                    {[
                        record.date ? formatDate(record.date) : null,
                        record.project,
                        record.series,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {labels.length > 0 && (
                    <p className="line-clamp-1 text-xs text-muted-foreground">
                        {labels.join(' · ')}
                    </p>
                )}
            </div>
        </Link>
    );
}
