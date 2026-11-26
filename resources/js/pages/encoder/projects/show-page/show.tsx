import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    ListPlus,
    MapPin,
    Upload,
} from 'lucide-react';
import { toast } from 'sonner';
import SimplePagination from '@/components/all/simple-pagination';
import ProcessingStatusBadge from '@/components/all/processing-status-badge';
import ProjectFormDialog from '@/components/encoder/project-form-dialog';
import ReviewStatusBadge from '@/components/all/review-status-badge';
import { Button } from '@/components/ui/button';
import { useCreateSeries } from '@/hooks/use-projects';
import { formatDate, formatDateRange, formatPlace } from '@/lib/utilities';
import { index, show } from '@/routes/projects';
import { create as newRecord, show as showRecord } from '@/routes/records';
import type { Paginated } from '@/types/pagination';
import type {
    ProjectDetail,
    ProjectRecordItem,
    ProjectSeriesItem,
} from '@/types/projects';
import DeleteProjectButton from './partials/delete-project-button';
import EditProjectButton from './partials/edit-project-button';

type Props = {
    project: ProjectDetail;
    series: ProjectSeriesItem[];
    records: Paginated<ProjectRecordItem>;
    canEdit: boolean;
    canDelete: boolean;
};

/**
 * One project: its details, its series, and the videos filed under it.
 */
export default function ProjectShow({
    project,
    series,
    records,
    canEdit,
    canDelete,
}: Props) {
    const createSeries = useCreateSeries(project.id);

    return (
        <>
            <Head title={project.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 flex-wrap gap-4">
                        {project.thumbnail_url && (
                            <img
                                src={project.thumbnail_url}
                                alt=""
                                className="aspect-video w-48 shrink-0 rounded-lg border object-cover"
                            />
                        )}
                        <div className="min-w-0 space-y-2">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {project.title}
                            </h1>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <CalendarDays
                                        className="size-4"
                                        aria-hidden
                                    />
                                    {formatDateRange(
                                        project.date,
                                        project.end_date,
                                    )}
                                </span>
                                {project.agency && (
                                    <span
                                        className="flex items-center gap-1"
                                        title={project.agency.name}
                                    >
                                        <Building2
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {project.agency.acronym ??
                                            project.agency.name}
                                    </span>
                                )}
                                {formatPlace(
                                    project.location,
                                    project.place,
                                ) && (
                                    <span className="flex items-center gap-1">
                                        <MapPin
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {formatPlace(
                                            project.location,
                                            project.place,
                                        )}
                                    </span>
                                )}
                            </div>
                            {project.description && (
                                <p className="max-w-3xl text-justify text-sm hyphens-auto whitespace-pre-line">
                                    {project.description}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && <EditProjectButton project={project} />}
                        {canDelete && <DeleteProjectButton project={project} />}
                        <Button asChild>
                            <Link
                                href={newRecord({
                                    query: { project: project.id },
                                })}
                            >
                                <Upload aria-hidden />
                                Upload to this project
                            </Link>
                        </Button>
                    </div>
                </header>

                <section aria-labelledby="series-heading" className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 id="series-heading" className="font-medium">
                            Series
                        </h2>
                        <ProjectFormDialog
                            trigger={
                                <Button variant="outline" size="sm">
                                    <ListPlus aria-hidden />
                                    New series
                                </Button>
                            }
                            heading="New series"
                            explanation={`A day, session or episode run inside ${project.title}.`}
                            titlePlaceholder="e.g. Day 1 — Opening"
                            submitLabel="Create series"
                            variant="series"
                            pending={createSeries.isPending}
                            onSubmit={(values) =>
                                createSeries
                                    .mutateAsync(values)
                                    .then((created) => {
                                        toast.success(
                                            `Created ${created.title}.`,
                                        );
                                        router.reload({ only: ['series'] });
                                    })
                            }
                        />
                    </div>
                    {series.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            No series. Add one if the event has days, sessions
                            or episodes.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {series.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {item.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDateRange(
                                                item.date,
                                                item.end_date,
                                            )}
                                            {formatPlace(
                                                item.location,
                                                item.place,
                                            ) &&
                                                ` · ${formatPlace(item.location, item.place)}`}{' '}
                                            · {item.records_count} videos
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={newRecord({
                                                query: {
                                                    project: project.id,
                                                    series: item.id,
                                                },
                                            })}
                                        >
                                            <Upload aria-hidden />
                                            Upload to this series
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section
                    aria-labelledby="records-heading"
                    className="space-y-3"
                >
                    <h2 id="records-heading" className="font-medium">
                        Videos
                    </h2>
                    {records.data.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            No videos filed here yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {records.data.map((record) => (
                                <li
                                    key={record.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <Link
                                            href={showRecord(record.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {record.title}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {record.series ?? 'No series'} ·{' '}
                                            {formatDate(record.created_at)}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <ProcessingStatusBadge
                                            status={record.processing_status}
                                        />
                                        <ReviewStatusBadge
                                            status={record.review_status}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <SimplePagination page={records} />
                </section>
            </div>
        </>
    );
}

ProjectShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: props.project.title, href: show(props.project.id) },
    ],
});
