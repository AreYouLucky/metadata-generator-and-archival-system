import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useReopenRecord } from '@/hooks/use-review-record';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    recordId: string;
    /** Inertia props to reload once reopened. */
    reloadProps?: string[];
};

/**
 * Admins: put an approved or rejected record back to draft. An approved one
 * leaves the public catalogue until it is approved again.
 */
export default function ReopenRecordButton({ recordId, reloadProps }: Props) {
    const [open, setOpen] = useState(false);
    const [notes, setNotes] = useState('');
    const reopen = useReopenRecord(recordId, reloadProps);

    const confirm = () =>
        reopen.mutate(notes, {
            onSuccess: () => {
                setOpen(false);
                setNotes('');
                toast.success('Reopened as a draft.');
            },
            onError: (error) => toast.error(getErrorMessage(error)),
        });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <RotateCcw aria-hidden />
                    Reopen as draft
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reopen this record?</DialogTitle>
                    <DialogDescription>
                        It goes back to draft so it can be edited, and it is
                        removed from the public catalogue until it is approved
                        again.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label htmlFor="reopen-notes">Note (optional)</Label>
                    <Textarea
                        id="reopen-notes"
                        maxLength={2000}
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        placeholder="Why it is being reopened"
                    />
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button disabled={reopen.isPending} onClick={confirm}>
                        {reopen.isPending && <Spinner />}
                        Reopen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
