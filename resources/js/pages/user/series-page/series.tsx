import { Head, Link } from '@inertiajs/react';
import { CalendarRange, MapPin } from 'lucide-react';
import SimplePagination from '@/components/all/simple-pagination';
import RecordGrid from '@/components/user/record-grid';
import { formatDateRange, formatPlace } from '@/lib/utilities';
import { show } from '@/routes/catalogue/projects';
import type { CataloguePage, CatalogueProject } from '@/types/catalogue';

type Props = {
    series: {
        id: string;
        title: string;
        description: string | null;
        date: string | null;
        end_date: string | null;
        place: string | null;
        location: string | null;
    };
    project: CatalogueProject | null;
    records: CataloguePage;
};

/**
 * One series (a day, session or episode run) of an event, with its videos.
 */
export default function Series({ series, project, records }: Props) {
    const place = formatPlace(series.location, series.place);

    return (
        <>
            <Head title={series.title} />
            <div className="grid gap-8">
                <header className="grid gap-3">
                    {project && (
                        <Link
                            href={show(project.id)}
                            className="text-sm text-brand hover:underline"
                        >
                            {project.title}
                        </Link>
                    )}
                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        {series.title}
                    </h1>
                    <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        {series.date && (
                            <span className="inline-flex items-center gap-1.5">
                                <CalendarRange className="size-4" aria-hidden />
                                {formatDateRange(series.date, series.end_date)}
                            </span>
                        )}
                        {place && (
                            <span className="inline-flex items-center gap-1.5">
                                <MapPin className="size-4" aria-hidden />
                                {place}
                            </span>
                        )}
                    </p>
                    {series.description && (
                        <p className="max-w-3xl whitespace-pre-line">
                            {series.description}
                        </p>
                    )}
                </header>

                <RecordGrid
                    records={records.data}
                    empty="No published videos here yet."
                />
                <SimplePagination page={records} />
            </div>
        </>
    );
}
