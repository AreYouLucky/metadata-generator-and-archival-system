import { MessageSquare, Trash2 } from 'lucide-react';
import { formatTimeAgo } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import type { ChatConversation } from '@/types/chat';

type Props = {
    conversation: ChatConversation;
    active: boolean;
    deleting: boolean;
    onOpen: () => void;
    onDelete: () => void;
};

/** One saved conversation: open it, or delete it. */
export default function ChatHistoryItem({
    conversation,
    active,
    deleting,
    onOpen,
    onDelete,
}: Props) {
    return (
        <li
            className={cn(
                'group flex items-center gap-1 rounded-lg transition-colors',
                active ? 'bg-brand/10' : 'hover:bg-muted/70',
            )}
        >
            <button
                type="button"
                onClick={onOpen}
                aria-current={active ? 'true' : undefined}
                className="flex min-w-0 flex-1 items-start gap-2.5 rounded-lg px-3 py-2.5 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <MessageSquare
                    className={cn(
                        'mt-0.5 size-3.5 shrink-0',
                        active ? 'text-brand' : 'text-muted-foreground',
                    )}
                    aria-hidden
                />
                <span className="grid min-w-0 gap-0.5">
                    <span className="truncate text-sm font-medium">
                        {conversation.title}
                    </span>
                    <span className="text-[11px] text-muted-foreground">
                        {formatTimeAgo(conversation.updated_at)}
                    </span>
                </span>
            </button>
            <button
                type="button"
                onClick={onDelete}
                disabled={deleting}
                className="mr-1.5 rounded-md p-1.5 text-muted-foreground opacity-0 transition group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                aria-label={`Delete “${conversation.title}”`}
                title="Delete this chat"
            >
                <Trash2 className="size-3.5" />
            </button>
        </li>
    );
}
