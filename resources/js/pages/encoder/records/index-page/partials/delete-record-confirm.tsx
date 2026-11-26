import { router } from '@inertiajs/react';
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
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useDeleteRecord } from '@/hooks/use-delete-record';
import { getErrorMessage } from '@/lib/utilities';
import type { RecordListItem } from '@/types/records';

type Props = {
    record: RecordListItem;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Confirms a soft delete from the record list, then reloads the rows.
 */
export default function DeleteRecordConfirm({
    record,
    open,
    onOpenChange,
}: Props) {
    const deleteRecord = useDeleteRecord(record.id);
    const processing =
        record.processing_status === 'queued' ||
        record.processing_status === 'processing';

    const confirm = () =>
        deleteRecord.mutate(undefined, {
            onSuccess: () => {
                onOpenChange(false);
                toast.success('Deleted ' + record.title + '.');
                router.reload({ only: ['records'] });
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The record could not be deleted.'),
                ),
        });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {record.title}?</DialogTitle>
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
