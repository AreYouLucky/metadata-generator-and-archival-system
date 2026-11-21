import { Link } from '@inertiajs/react';
import { Building2, CalendarDays, ImageIcon, MapPin } from 'lucide-react';
import { formatDateRange, formatPlace } from '@/lib/utilities';
import { show } from '@/routes/projects';
import type { ProjectListItem } from '@/types/projects';

type Props = {
    project: ProjectListItem;
};

/**
 * One project in the grid: cover, name, dates, agency, place and counts.
 */
export default function ProjectCard({ project }: Props) {
    const place = formatPlace(project.location, project.place);

    return (
        <Link
            href={show(project.id)}
            className="block h-full overflow-hidden rounded-xl border transition-colors hover:border-brand/60 hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="flex aspect-video items-center justify-center bg-muted">
                {project.thumbnail_url ? (
                    <img
                        src={project.thumbnail_url}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <ImageIcon
                        className="size-8 text-muted-foreground/50"
                        aria-hidden
                    />
                )}
            </div>
            <div className="p-4">
                <p className="font-medium">{project.title}</p>
                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1">
                        <CalendarDays className="size-3.5" aria-hidden />
                        {formatDateRange(project.date, project.end_date)}
                    </span>
                    {project.agency && (
                        <span
                            className="flex items-center gap-1"
                            title={project.agency.name}
                        >
                            <Building2 className="size-3.5" aria-hidden />
                            {project.agency.acronym ?? project.agency.name}
                        </span>
                    )}
                    {place && (
                        <span className="flex items-center gap-1">
                            <MapPin className="size-3.5" aria-hidden />
                            {place}
                        </span>
                    )}
                </div>
                <p className="mt-3 text-xs text-muted-foreground">
                    {project.series_count} series · {project.records_count}{' '}
                    videos
                </p>
            </div>
        </Link>
    );
}
