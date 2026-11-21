import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useDeleteProject } from '@/hooks/use-projects';
import { getErrorMessage } from '@/lib/utilities';
import { index } from '@/routes/projects';
import type { ProjectDetail } from '@/types/projects';

type Props = {
    project: ProjectDetail;
};

/**
 * Soft-delete the project after a confirmation, then go back to the list.
 * Shown only when the policy allows it.
 */
export default function DeleteProjectButton({ project }: Props) {
    const [open, setOpen] = useState(false);
    const deleteProject = useDeleteProject(project.id);

    const confirm = () =>
        deleteProject.mutate(undefined, {
            onSuccess: () => {
                setOpen(false);
                toast.success(`Deleted ${project.title}.`);
                router.visit(index());
            },
            onError: (error) => toast.error(getErrorMessage(error)),
        });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="text-destructive">
                    <Trash2 aria-hidden />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {project.title}?</DialogTitle>
                    <DialogDescription>
                        The project and its series disappear from the lists and
                        the upload picker. Videos already filed under it stay in
                        the archive and keep its name. Nothing is erased, so it
                        can be restored later.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={deleteProject.isPending}
                        onClick={confirm}
                    >
                        {deleteProject.isPending && <Spinner />}
                        Delete project
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
