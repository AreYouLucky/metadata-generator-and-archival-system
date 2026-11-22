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
import { useDeleteRecord } from '@/hooks/use-delete-record';
import { getErrorMessage } from '@/lib/utilities';
import { index } from '@/routes/records';

type Props = {
    recordId: string;
    title: string;
    processing: boolean;
};

/**
 * Soft-deletes the record after a confirmation, then goes back to the record
 * list. Shown only when the policy allows it.
 */
export default function DeleteRecordDialog({
    recordId,
    title,
    processing,
}: Props) {
    const [open, setOpen] = useState(false);
    const deleteRecord = useDeleteRecord(recordId);

    const confirm = () =>
        deleteRecord.mutate(undefined, {
            onSuccess: () => {
                setOpen(false);
                toast.success(`Deleted ${title}.`);
                router.visit(index());
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The record could not be deleted.'),
                ),
        });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-7 text-destructive"
                    aria-label="Delete record"
                    title="Delete record"
                >
                    <Trash2 aria-hidden />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {title}?</DialogTitle>
                    <DialogDescription>
                        {processing &&
                            'Processing is still running and will be cancelled. '}
                        The record leaves your list and the public site. Its
                        audit trail is kept, so an administrator can still see
                        that it existed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={deleteRecord.isPending}
                        onClick={confirm}
                    >
                        {deleteRecord.isPending && <Spinner />}
                        Delete record
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
