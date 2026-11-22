import { Send } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useSubmitRecord } from '@/hooks/use-review-record';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    recordId: string;
    /** Processing must have finished before a record can be reviewed. */
    ready: boolean;
    /** Unconfirmed AI subjects and keywords (never public unless confirmed). */
    pendingSuggestions: number;
};

/**
 * "Submit for approval": sends the record to the admins' review queue, with
 * an optional note for the reviewer. It is read-only afterwards.
 */
export default function SubmitRecordDialog({
    recordId,
    ready,
    pendingSuggestions,
}: Props) {
    const [open, setOpen] = useState(false);
    const [notes, setNotes] = useState('');
    const [confirmSuggestions, setConfirmSuggestions] = useState(true);
    const submit = useSubmitRecord(recordId);

    const confirm = () =>
        submit.mutate(
            {
                notes,
                confirmSuggestions:
                    pendingSuggestions > 0 && confirmSuggestions,
            },
            {
                onSuccess: () => {
                    setOpen(false);
                    setNotes('');
                    toast.success('Submitted for approval.');
                },
                onError: (error) => toast.error(getErrorMessage(error)),
            },
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    className="h-7"
                    disabled={!ready}
                    title={
                        ready
                            ? 'Send to an admin for approval'
                            : 'Available once processing has finished'
                    }
                >
                    <Send aria-hidden />
                    <span className="hidden sm:inline">Submit</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Submit for approval?</DialogTitle>
                    <DialogDescription>
                        An admin reviews the transcript, metadata and archive
                        record. You can&apos;t edit it while it waits; if
                        changes are requested it comes back to you with notes.
                    </DialogDescription>
                </DialogHeader>
                {pendingSuggestions > 0 && (
                    <div className="grid gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        <p>
                            {pendingSuggestions} AI subject and keyword
                            suggestion
                            {pendingSuggestions === 1 ? ' is' : 's are'} not
                            confirmed. Unconfirmed suggestions are never shown
                            publicly.
                        </p>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="submit-confirm-suggestions"
                                checked={confirmSuggestions}
                                onCheckedChange={(value) =>
                                    setConfirmSuggestions(value === true)
                                }
                            />
                            <Label htmlFor="submit-confirm-suggestions">
                                Confirm them now
                            </Label>
                        </div>
                    </div>
                )}
                <div className="grid gap-2">
                    <Label htmlFor="submit-notes">
                        Note for the reviewer (optional)
                    </Label>
                    <Textarea
                        id="submit-notes"
                        maxLength={2000}
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        placeholder="Anything the reviewer should know"
                    />
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button disabled={submit.isPending} onClick={confirm}>
                        {submit.isPending && <Spinner />}
                        Submit
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
