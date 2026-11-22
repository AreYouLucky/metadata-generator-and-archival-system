import { Link } from '@inertiajs/react';
import {
    FolderOpen,
    Layers,
    MoreHorizontal,
    RotateCcw,
    Square,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCancelProcessing } from '@/hooks/use-cancel-processing';
import { getErrorMessage } from '@/lib/utilities';
import { show as showBatch } from '@/routes/batches';
import { show } from '@/routes/records';
import type { RecordListItem } from '@/types/records';
import DeleteRecordConfirm from './delete-record-confirm';

type Props = {
    record: RecordListItem;
};

/**
 * Row actions in the record list: open it (or its batch), stop processing
 * while it runs, retry (in the workspace, where the options are chosen) and
 * delete. Shown actions follow the policy; the server checks again.
 */
export default function RecordRowActions({ record }: Props) {
    const [deleting, setDeleting] = useState(false);
    const cancel = useCancelProcessing(record.id, ['records']);
    const working =
        record.processing_status === 'queued' ||
        record.processing_status === 'processing';
    const stopped =
        record.processing_status === 'failed' ||
        record.processing_status === 'cancelled';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={'Actions for ' + record.title}
                    >
                        <MoreHorizontal aria-hidden />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link href={show(record.id)}>
                            <FolderOpen aria-hidden />
                            Open
                        </Link>
                    </DropdownMenuItem>
                    {record.batch && (
                        <DropdownMenuItem asChild>
                            <Link href={showBatch(record.batch.id)}>
                                <Layers aria-hidden />
                                Open batch
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {record.can_delete && stopped && (
                        <DropdownMenuItem asChild>
                            <Link href={show(record.id)}>
                                <RotateCcw aria-hidden />
                                Retry in the workspace
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {record.can_delete && working && (
                        <DropdownMenuItem
                            disabled={cancel.isPending}
                            onSelect={() =>
                                cancel.mutate(undefined, {
                                    onSuccess: () =>
                                        toast.success('Processing stopped.'),
                                    onError: (error) =>
                                        toast.error(
                                            getErrorMessage(
                                                error,
                                                'Processing could not be stopped.',
                                            ),
                                        ),
                                })
                            }
                        >
                            <Square aria-hidden />
                            Stop processing
                        </DropdownMenuItem>
                    )}
                    {record.can_delete && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setDeleting(true)}
                            >
                                <Trash2 aria-hidden />
                                Delete…
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            {record.can_delete && (
                <DeleteRecordConfirm
                    record={record}
                    open={deleting}
                    onOpenChange={setDeleting}
                />
            )}
        </>
    );
}
