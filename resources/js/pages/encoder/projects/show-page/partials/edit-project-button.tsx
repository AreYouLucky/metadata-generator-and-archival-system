import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { toast } from 'sonner';
import ProjectFormDialog from '@/components/encoder/project-form-dialog';
import { Button } from '@/components/ui/button';
import { useUpdateProject } from '@/hooks/use-projects';
import type { ProjectDetail } from '@/types/projects';

type Props = {
    project: ProjectDetail;
};

/**
 * Edit the project's details, agency and thumbnail (creator or admin only).
 */
export default function EditProjectButton({ project }: Props) {
    const updateProject = useUpdateProject(project.id);

    return (
        <ProjectFormDialog
            trigger={
                <Button variant="outline">
                    <Pencil aria-hidden />
                    Edit
                </Button>
            }
            heading="Edit project"
            explanation="Changes apply to every video filed under this project."
            titlePlaceholder="e.g. Regional S&T Fair 2026"
            submitLabel="Save changes"
            pending={updateProject.isPending}
            initial={{
                title: project.title,
                date: project.date ?? '',
                end_date: project.end_date ?? '',
                description: project.description ?? '',
                location: project.location ?? '',
                agency_id: project.agency?.id ?? null,
                region_id: project.region_id,
                prov_id: project.prov_id,
                city_id: project.city_id,
                brgy_id: project.brgy_id,
            }}
            currentThumbnailUrl={project.thumbnail_url}
            onSubmit={(values) =>
                updateProject.mutateAsync(values).then((saved) => {
                    toast.success(`Saved ${saved.title}.`);
                    router.reload({ only: ['project'] });
                })
            }
        />
    );
}
