import { Pencil, Trash2 } from 'lucide-react';
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
import { useDeleteLookup, useUpdateLookup } from '@/hooks/use-lookups';
import { getErrorMessage } from '@/lib/utilities';
import type { LookupItem, LookupKind } from '@/types/lookups';
import LookupFormDialog from './lookup-form-dialog';

type Props = {
    kind: LookupKind;
    item: LookupItem;
    noun: string;
};

/**
 * One genre or video type: rename it, or delete it while no record uses it.
 */
export default function LookupRow({ kind, item, noun }: Props) {
    const [confirming, setConfirming] = useState(false);
    const update = useUpdateLookup(kind);
    const remove = useDeleteLookup(kind);
    const inUse = item.records_count > 0;

    const confirmDelete = () =>
        remove.mutate(item.id, {
            onSuccess: () => {
                setConfirming(false);
                toast.success(`Deleted ${item.name}.`);
            },
            onError: (error) => toast.error(getErrorMessage(error)),
        });

    return (
        <li className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
                <p className="font-medium">{item.name}</p>
                <p className="text-xs text-muted-foreground">
                    Position {item.sort_order} · {item.slug} ·{' '}
                    {item.records_count === 1
                        ? '1 record'
                        : `${item.records_count} records`}
                </p>
            </div>
            <div className="flex items-center gap-1">
                <LookupFormDialog
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Rename ${item.name}`}
                        >
                            <Pencil aria-hidden />
                        </Button>
                    }
                    heading={`Edit ${noun}`}
                    submitLabel="Save"
                    pending={update.isPending}
                    initial={{ name: item.name, sort_order: item.sort_order }}
                    onSubmit={(values) =>
                        update
                            .mutateAsync({ id: item.id, ...values })
                            .then(() => toast.success('Saved.'))
                    }
                />
                <Dialog open={confirming} onOpenChange={setConfirming}>
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-destructive"
                            disabled={inUse}
                            title={
                                inUse
                                    ? 'Records use it, so it cannot be deleted. Rename it instead.'
                                    : `Delete ${item.name}`
                            }
                            aria-label={`Delete ${item.name}`}
                        >
                            <Trash2 aria-hidden />
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete {item.name}?</DialogTitle>
                            <DialogDescription>
                                No record uses it, so it can be removed for
                                good.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="ghost">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                disabled={remove.isPending}
                                onClick={confirmDelete}
                            >
                                {remove.isPending && <Spinner />}
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </li>
    );
}
