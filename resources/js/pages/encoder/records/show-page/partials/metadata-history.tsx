import { History } from 'lucide-react';
import { toast } from 'sonner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useActivateMetadata } from '@/hooks/use-update-metadata';
import { formatClockTime, formatDate, getErrorMessage } from '@/lib/utilities';
import type { MetadataHistoryItem } from '@/types/records';

type Props = {
    recordId: string;
    history: MetadataHistoryItem[];
    disabled: boolean;
};

/**
 * Switch back to an earlier generation. Disabled while there are unsaved edits.
 */
export default function MetadataHistory({
    recordId,
    history,
    disabled,
}: Props) {
    const activate = useActivateMetadata(recordId);
    const active = history.find((item) => item.is_active);

    return (
        <Select
            value={active ? String(active.id) : undefined}
            disabled={disabled || activate.isPending}
            onValueChange={(value) =>
                activate.mutate(Number(value), {
                    onSuccess: () =>
                        toast.success('Switched to that generation.'),
                    onError: (error) => toast.error(getErrorMessage(error)),
                })
            }
        >
            <SelectTrigger
                className="h-7 w-auto gap-1 border-none bg-transparent px-2 text-xs shadow-none dark:bg-transparent"
                aria-label="Generation history"
                title="Earlier generations"
            >
                <History className="size-3.5" aria-hidden />
                <span className="sr-only">
                    <SelectValue placeholder="History" />
                </span>
            </SelectTrigger>
            <SelectContent>
                {history.map((item) => (
                    <SelectItem
                        key={item.id}
                        value={String(item.id)}
                        className="text-xs"
                    >
                        {item.preset} · {formatDate(item.created_at)}
                        {item.created_at &&
                            ` ${formatClockTime(item.created_at)}`}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
