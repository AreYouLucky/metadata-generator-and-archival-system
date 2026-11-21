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
import { useDeleteAgency } from '@/hooks/use-agencies';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    id: number;
    name: string;
    /** Still used by records, projects or units: deleting is refused. */
    inUse: boolean;
};

/**
 * Delete an unused agency or unit after a confirmation. Used ones can only be
 * renamed, so archival credits are never lost.
 */
export default function DeleteAgencyButton({ id, name, inUse }: Props) {
    const [open, setOpen] = useState(false);
    const deleteAgency = useDeleteAgency();

    const confirm = () =>
        deleteAgency.mutate(id, {
            onSuccess: () => {
                setOpen(false);
                toast.success(`Deleted ${name}.`);
            },
            onError: (error) => toast.error(getErrorMessage(error)),
        });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-destructive"
                    disabled={inUse}
                    title={
                        inUse
                            ? 'In use, so it cannot be deleted. Rename it instead.'
                            : `Delete ${name}`
                    }
                    aria-label={`Delete ${name}`}
                >
                    <Trash2 aria-hidden />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {name}?</DialogTitle>
                    <DialogDescription>
                        Nothing uses it yet, so it can be removed for good.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={deleteAgency.isPending}
                        onClick={confirm}
                    >
                        {deleteAgency.isPending && <Spinner />}
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
