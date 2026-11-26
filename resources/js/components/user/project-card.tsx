import { Link } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDateRange } from '@/lib/utilities';
import { show } from '@/routes/catalogue/projects';
import type { CatalogueProject } from '@/types/catalogue';

/**
 * An event or programme in the public catalogue, with its dates and how many
 * public videos it has.
 */
export default function ProjectCard({
    project,
}: {
    project: CatalogueProject;
}) {
    return (
        <Link
            href={show(project.id)}
            className="group flex gap-4 rounded-xl border bg-white/85 p-3 transition hover:border-brand/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="aspect-video w-32 shrink-0 overflow-hidden rounded-lg bg-muted">
                {project.thumbnail ? (
                    <img
                        src={project.thumbnail}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand-muted to-muted">
                        <CalendarRange
                            className="size-6 text-brand/60"
                            aria-hidden
                        />
                    </div>
                )}
            </div>
            <div className="grid min-w-0 content-start gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="line-clamp-2 font-medium group-hover:underline">
                        {project.title}
                    </h3>
                    {project.is_current && <Badge>Happening now</Badge>}
                </div>
                <p className="text-xs text-muted-foreground">
                    {formatDateRange(project.date, project.end_date)}
                </p>
                {project.count !== undefined && (
                    <p className="text-xs text-muted-foreground">
                        {project.count === 1
                            ? '1 video'
                            : `${project.count} videos`}
                    </p>
                )}
            </div>
        </Link>
    );
}
