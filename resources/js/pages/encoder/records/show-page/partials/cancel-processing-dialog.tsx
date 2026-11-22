import { CircleStop } from 'lucide-react';
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
import { useCancelProcessing } from '@/hooks/use-cancel-processing';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    recordId: string;
};

/**
 * Stops the pipeline after a confirmation. Steps already finished keep their
 * results; the rest are marked cancelled and can be run again with Retry.
 */
export default function CancelProcessingDialog({ recordId }: Props) {
    const [open, setOpen] = useState(false);
    const cancelProcessing = useCancelProcessing(recordId);

    const confirm = () =>
        cancelProcessing.mutate(undefined, {
            onSuccess: () => {
                setOpen(false);
                toast.success('Processing cancelled.');
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(
                        error,
                        'Processing could not be cancelled.',
                    ),
                ),
        });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost" className="h-7">
                    <CircleStop aria-hidden />
                    <span className="hidden sm:inline">Cancel</span>
                    <span className="sr-only sm:hidden">Cancel processing</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel processing?</DialogTitle>
                    <DialogDescription>
                        The steps still to run are stopped, so no more AI usage
                        is spent on this video. Steps that already finished keep
                        their results. You can run everything again later with
                        Retry.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Keep processing</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={cancelProcessing.isPending}
                        onClick={confirm}
                    >
                        {cancelProcessing.isPending && <Spinner />}
                        Cancel processing
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
