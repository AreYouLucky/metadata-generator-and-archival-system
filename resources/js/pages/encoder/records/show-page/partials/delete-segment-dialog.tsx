import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useDeleteSegment } from '@/hooks/use-delete-segment';
import { formatTimestamp, getErrorMessage } from '@/lib/utilities';
import type { TranscriptSegment } from '@/types/records';

type Props = {
    recordId: string;
    segment: TranscriptSegment | null;
    onClose: () => void;
    onDeleted: (segmentId: number) => void;
};

/**
 * Confirms deleting a transcript line. One dialog serves the whole list.
 */
export default function DeleteSegmentDialog({
    recordId,
    segment,
    onClose,
    onDeleted,
}: Props) {
    const remove = useDeleteSegment(recordId);

    const confirm = () => {
        if (!segment) {
            return;
        }

        remove.mutate(segment.id, {
            onSuccess: (segmentId) => {
                onDeleted(segmentId);
                onClose();
                toast.success('Line deleted.');
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The line could not be deleted.'),
                ),
        });
    };

    return (
        <Dialog
            open={segment !== null}
            onOpenChange={(open) => !open && !remove.isPending && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete this line?</DialogTitle>
                    <DialogDescription>
                        The line at{' '}
                        {segment ? formatTimestamp(segment.start) : ''} is
                        removed from the transcript, its exports and future
                        metadata. This can't be undone.
                    </DialogDescription>
                </DialogHeader>
                {segment && (
                    <blockquote className="max-h-32 overflow-y-auto rounded-md bg-muted/50 p-3 text-sm">
                        {segment.text}
                    </blockquote>
                )}
                <DialogFooter>
                    <Button
                        variant="ghost"
                        onClick={onClose}
                        disabled={remove.isPending}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={confirm}
                        disabled={remove.isPending}
                        autoFocus
                    >
                        {remove.isPending && <Spinner />}
                        Delete line
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
