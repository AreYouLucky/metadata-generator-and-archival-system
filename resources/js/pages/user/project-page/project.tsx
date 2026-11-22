import { Head, Link } from '@inertiajs/react';
import { CalendarRange, MapPin } from 'lucide-react';
import SimplePagination from '@/components/all/simple-pagination';
import RecordGrid from '@/components/user/record-grid';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { formatDateRange, formatPlace } from '@/lib/utilities';
import { show } from '@/routes/catalogue/projects';
import { show as showSeries } from '@/routes/catalogue/series';
import type {
    CataloguePage,
    CatalogueProject,
    CatalogueSeriesOption,
} from '@/types/catalogue';

type Props = {
    project: CatalogueProject & {
        description: string | null;
        agency: string | null;
        place: string | null;
        location: string | null;
    };
    series: CatalogueSeriesOption[];
    records: CataloguePage;
    activeSeries: string | null;
};

/**
 * An event or programme: its dates, place and agency, its series as tabs,
 * and its published videos.
 */
export default function Project({
    project,
    series,
    records,
    activeSeries,
}: Props) {
    const place = formatPlace(project.location, project.place);
    const tab = (active: boolean) =>
        cn(
            'shrink-0 rounded-full border px-3 py-1 text-sm transition',
            active
                ? 'border-brand bg-brand text-brand-foreground'
                : 'hover:border-brand/50 hover:bg-brand-muted',
        );

    return (
        <>
            <Head title={project.title} />
            <div className="grid gap-8">
                <header className="grid gap-6 md:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="grid content-start gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-3xl font-semibold tracking-tight text-balance">
                                {project.title}
                            </h1>
                            {project.is_current && <Badge>Happening now</Badge>}
                        </div>
                        <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <CalendarRange className="size-4" aria-hidden />
                                {formatDateRange(
                                    project.date,
                                    project.end_date,
                                )}
                            </span>
                            {place && (
                                <span className="inline-flex items-center gap-1.5">
                                    <MapPin className="size-4" aria-hidden />
                                    {place}
                                </span>
                            )}
                            {project.agency && <span>{project.agency}</span>}
                        </p>
                        {project.description && (
                            <p className="max-w-3xl whitespace-pre-line">
                                {project.description}
                            </p>
                        )}
                    </div>
                    {project.thumbnail && (
                        <img
                            src={project.thumbnail}
                            alt=""
                            className="aspect-video w-full rounded-xl border object-cover"
                        />
                    )}
                </header>

                {series.length > 0 && (
                    <nav
                        aria-label="Series"
                        className="flex gap-2 overflow-x-auto pb-1"
                    >
                        <Link
                            href={show(project.id)}
                            preserveScroll
                            className={tab(activeSeries === null)}
                        >
                            All videos
                        </Link>
                        {series.map((item) => (
                            <Link
                                key={item.id}
                                href={show(project.id, {
                                    query: { series: item.id },
                                })}
                                preserveScroll
                                className={tab(activeSeries === item.id)}
                            >
                                {item.title}
                                <span className="ml-1 text-xs opacity-70">
                                    {item.count}
                                </span>
                            </Link>
                        ))}
                    </nav>
                )}

                {activeSeries && (
                    <p className="-mt-4 text-sm">
                        <Link
                            href={showSeries(activeSeries)}
                            className="text-brand hover:underline"
                        >
                            Open the series page
                        </Link>
                    </p>
                )}

                <RecordGrid
                    records={records.data}
                    empty="No published videos here yet."
                />
                <SimplePagination page={records} />
            </div>
        </>
    );
}
