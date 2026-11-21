import { Head, router } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import { toast } from 'sonner';
import SimplePagination from '@/components/all/simple-pagination';
import ListSearch from '@/components/encoder/list-search';
import ProjectFormDialog from '@/components/encoder/project-form-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCreateProject } from '@/hooks/use-projects';
import { index } from '@/routes/projects';
import type { Paginated } from '@/types/pagination';
import type { ProjectListItem } from '@/types/projects';
import ProjectCard from './partials/project-card';

type Props = {
    projects: Paginated<ProjectListItem>;
    filters: { q: string };
};

/**
 * Projects (events or programmes). Every uploaded video is filed under one.
 */
export default function ProjectsIndex({ projects, filters }: Props) {
    const createProject = useCreateProject();

    return (
        <>
            <Head title="Projects" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Projects"
                        description="Events and programmes. Every video is filed under a project, and optionally under one of its series (a day, session or episode run)."
                    />
                    <ProjectFormDialog
                        trigger={
                            <Button>
                                <FolderPlus aria-hidden />
                                New project
                            </Button>
                        }
                        heading="New project"
                        explanation="An event or programme that groups videos, e.g. a conference or a campaign."
                        titlePlaceholder="e.g. Regional S&T Fair 2026"
                        submitLabel="Create project"
                        pending={createProject.isPending}
                        onSubmit={(values) =>
                            createProject
                                .mutateAsync(values)
                                .then((project) => {
                                    toast.success(`Created ${project.title}.`);
                                    router.reload({ only: ['projects'] });
                                })
                        }
                    />
                </div>

                <ListSearch
                    url={index.url()}
                    initial={filters.q}
                    label="Search projects"
                />

                {projects.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">
                            {filters.q
                                ? 'No projects match.'
                                : 'No projects yet.'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create the event first, then upload its videos.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.data.map((project) => (
                            <li key={project.id}>
                                <ProjectCard project={project} />
                            </li>
                        ))}
                    </ul>
                )}

                <SimplePagination page={projects} />
            </div>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
