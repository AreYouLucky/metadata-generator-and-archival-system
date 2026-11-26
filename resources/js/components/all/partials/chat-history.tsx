import { SquarePen } from 'lucide-react';
import { useMemo } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    useClearChat,
    useConversations,
    useDeleteConversation,
} from '@/hooks/use-chat';
import { getErrorMessage } from '@/lib/utilities';
import ChatHistoryItem from './chat-history-item';

type Props = {
    activeId: number | null;
    busy: boolean;
    onOpen: (conversationId: number) => void;
    onNewChat: () => void;
    /** A conversation (or, with null, the whole history) was deleted. */
    onDeleted: (conversationId: number | null) => void;
};

/**
 * The saved conversations, most recently active first: open one to carry
 * on, start a new one, delete one, or clear them all.
 */
export default function ChatHistory({
    activeId,
    busy,
    onOpen,
    onNewChat,
    onDeleted,
}: Props) {
    const history = useConversations(true);
    const remove = useDeleteConversation();
    const clear = useClearChat();
    const conversations = useMemo(
        () => history.data?.pages.flatMap((page) => page.conversations) ?? [],
        [history.data],
    );

    const onDelete = (conversationId: number) =>
        remove.mutate(conversationId, {
            onSuccess: () => onDeleted(conversationId),
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The chat could not be deleted.'),
                ),
        });

    const onClear = () => {
        if (
            !window.confirm('Delete every saved chat? This cannot be undone.')
        ) {
            return;
        }

        clear.mutate(undefined, {
            onSuccess: () => onDeleted(null),
            onError: (error) =>
                toast.error(
                    getErrorMessage(
                        error,
                        'The chat history could not be cleared.',
                    ),
                ),
        });
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="px-4 pt-4 pb-2">
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-start gap-2"
                    onClick={onNewChat}
                >
                    <SquarePen className="size-4" />
                    New chat
                </Button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                {history.isPending ? (
                    <div className="flex justify-center py-10">
                        <Spinner />
                    </div>
                ) : history.isError ? (
                    <p className="px-1 py-4 text-sm text-destructive">
                        {getErrorMessage(
                            history.error,
                            'The chat history could not be loaded.',
                        )}
                    </p>
                ) : conversations.length === 0 ? (
                    <p className="px-1 py-10 text-center text-sm text-muted-foreground">
                        No saved chats yet. Your questions are kept here so you
                        can come back to them.
                    </p>
                ) : (
                    <>
                        <p className="px-3 pt-2 pb-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            Recent chats
                        </p>
                        <ul className="grid gap-0.5">
                            {conversations.map((conversation) => (
                                <ChatHistoryItem
                                    key={conversation.id}
                                    conversation={conversation}
                                    active={conversation.id === activeId}
                                    deleting={
                                        remove.isPending &&
                                        remove.variables === conversation.id
                                    }
                                    onOpen={() => onOpen(conversation.id)}
                                    onDelete={() => onDelete(conversation.id)}
                                />
                            ))}
                        </ul>
                        {history.hasNextPage && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="mt-2 w-full text-xs"
                                disabled={history.isFetchingNextPage}
                                onClick={() => void history.fetchNextPage()}
                            >
                                {history.isFetchingNextPage && <Spinner />}
                                Older chats
                            </Button>
                        )}
                    </>
                )}
            </div>
            {conversations.length > 0 && (
                <div className="border-t border-border px-4 py-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="w-full text-xs text-muted-foreground hover:text-destructive"
                        disabled={busy || clear.isPending}
                        onClick={onClear}
                    >
                        {clear.isPending && <Spinner />}
                        Clear all chats
                    </Button>
                </div>
            )}
        </div>
    );
}
