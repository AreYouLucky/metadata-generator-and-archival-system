import {
    CalendarDays,
    Check,
    FolderPlus,
    ListPlus,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ProjectFormDialog from '@/components/encoder/project-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    useCreateProject,
    useCreateSeries,
    useProjectOptions,
} from '@/hooks/use-projects';
import { formatDate, formatPlace } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { ProjectOption } from '@/types/projects';

const NO_SERIES = 'none';

type Props = {
    project: ProjectOption | null;
    seriesId: string | null;
    disabled: boolean;
    onChange: (project: ProjectOption | null, seriesId: string | null) => void;
};

/**
 * Required project (event) and optional series for the upload: search the
 * existing projects or create one on the spot, then pick or create a series.
 */
export default function ProjectPicker({
    project,
    seriesId,
    disabled,
    onChange,
}: Props) {
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const options = useProjectOptions(debounced);
    const createProject = useCreateProject();
    const createSeries = useCreateSeries(project?.id ?? '');

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(search.trim()), 300);

        return () => clearTimeout(timer);
    }, [search]);

    if (project) {
        return (
            <div className="grid gap-3">
                <div className="grid gap-2">
                    <Label>Project</Label>
                    <div className="flex items-start justify-between gap-2 rounded-md bg-brand/15 p-2 ring-1 ring-brand/40">
                        <div className="min-w-0">
                            <p className="flex items-center gap-1.5 text-sm font-medium">
                                <Check
                                    className="size-3.5 shrink-0 text-brand"
                                    aria-hidden
                                />
                                <span className="truncate">
                                    {project.title}
                                </span>
                            </p>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {formatDate(project.date)}
                                {formatPlace(project.location, project.place) &&
                                    ` · ${formatPlace(project.location, project.place)}`}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0"
                            disabled={disabled}
                            onClick={() => onChange(null, null)}
                            aria-label="Choose another project"
                        >
                            <X aria-hidden />
                        </Button>
                    </div>
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="series">Series (optional)</Label>
                        <ProjectFormDialog
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    disabled={disabled}
                                >
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
                                        onChange(
                                            {
                                                ...project,
                                                series: [
                                                    ...project.series,
                                                    created,
                                                ],
                                            },
                                            created.id,
                                        );
                                    })
                            }
                        />
                    </div>
                    <Select
                        value={seriesId ?? NO_SERIES}
                        disabled={disabled}
                        onValueChange={(value) =>
                            onChange(
                                project,
                                value === NO_SERIES ? null : value,
                            )
                        }
                    >
                        <SelectTrigger id="series">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_SERIES}>No series</SelectItem>
                            {project.series.map((series) => (
                                <SelectItem key={series.id} value={series.id}>
                                    {series.title}
                                    {series.date &&
                                        ` · ${formatDate(series.date)}`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <Label htmlFor="project-search">
                    Project <span className="text-red-400">*</span>
                </Label>
                <ProjectFormDialog
                    trigger={
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            disabled={disabled}
                        >
                            <FolderPlus aria-hidden />
                            New project
                        </Button>
                    }
                    heading="New project"
                    explanation="The event or programme this video belongs to, e.g. a conference or a campaign."
                    titlePlaceholder="e.g. Regional S&T Fair 2026"
                    submitLabel="Create project"
                    pending={createProject.isPending}
                    onSubmit={(values) =>
                        createProject.mutateAsync(values).then((created) => {
                            toast.success(`Created ${created.title}.`);
                            onChange(created, null);
                        })
                    }
                />
            </div>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <Input
                    id="project-search"
                    type="search"
                    value={search}
                    disabled={disabled}
                    placeholder="Search events and programmes"
                    className="pl-8"
                    onChange={(event) => setSearch(event.target.value)}
                />
            </div>
            <ul
                className={cn(
                    'max-h-56 overflow-y-auto rounded-md ring-1 ring-border',
                    options.isFetching && 'opacity-70',
                )}
                aria-label="Projects"
            >
                {options.isPending ? (
                    <li className="flex items-center gap-2 p-3 text-xs text-muted-foreground">
                        <Spinner /> Loading projects…
                    </li>
                ) : (options.data ?? []).length === 0 ? (
                    <li className="p-3 text-xs text-muted-foreground">
                        {debounced
                            ? 'No project matches. Create it with “New project”.'
                            : 'No projects yet. Create the event first.'}
                    </li>
                ) : (
                    options.data?.map((option) => (
                        <li key={option.id}>
                            <button
                                type="button"
                                disabled={disabled}
                                onClick={() => onChange(option, null)}
                                className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-muted/60 focus-visible:bg-muted focus-visible:outline-none"
                            >
                                <span className="text-sm">{option.title}</span>
                                <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <CalendarDays
                                        className="size-3"
                                        aria-hidden
                                    />
                                    {formatDate(option.date)} ·{' '}
                                    {option.series.length} series
                                </span>
                            </button>
                        </li>
                    ))
                )}
            </ul>
            <p className="text-[11px] text-muted-foreground">
                Every video is filed under a project (event).
            </p>
        </div>
    );
}
