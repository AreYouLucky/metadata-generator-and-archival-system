import { MessageCircleQuestion, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';
import EditorPanel from '@/components/encoder/editor-panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    useAskQuestion,
    useChat,
    useChatUpdates,
    useClearChat,
} from '@/hooks/use-chat';
import { getErrorMessage } from '@/lib/utilities';
import { useAskMeStore } from '@/stores/ask-me-store';
import ChatComposer from './chat-composer';
import ChatMessage from './chat-message';
import ChatSuggestions from './chat-suggestions';

type Props = {
    userId: number;
    /** The floating window is showing (focus the question box). */
    active: boolean;
    onClose: () => void;
};

/**
 * The "Ask me" conversation (Phase 7). The assistant looks things up with
 * the function tools (record search, transcripts, key frames, catalogue...)
 * within the approved records this account may see, and answers only from
 * what they return, with clickable moments. On a record's page, "this
 * video" means that record.
 */
export default function ChatPanel({ userId, active, onClose }: Props) {
    const chat = useChat(true);
    const ask = useAskQuestion();
    const clear = useClearChat();
    const pageRecordId = useAskMeStore((state) => state.page?.recordId ?? null);
    useChatUpdates(userId);

    // Pages arrive newest first; each page is oldest first.
    const messages = useMemo(
        () =>
            [...(chat.data?.pages ?? [])]
                .reverse()
                .flatMap((page) => page.messages),
        [chat.data],
    );
    const pending = chat.data?.pages[0]?.pending ?? false;
    const busy = pending || ask.isPending;

    // Follow the conversation as it grows, and when the window reopens.
    const end = useRef<HTMLDivElement>(null);
    const lastId = messages.at(-1)?.id;
    useEffect(() => {
        if (active) {
            end.current?.scrollIntoView({ block: 'end' });
        }
    }, [lastId, pending, active]);

    const onAsk = (question: string) =>
        ask
            .mutateAsync({ question, recordId: pageRecordId })
            .catch((error: unknown) => {
                toast.error(
                    getErrorMessage(error, 'The question could not be sent.'),
                );
                throw error;
            });

    const onClear = () =>
        clear.mutate(undefined, {
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The chat could not be cleared.'),
                ),
        });

    return (
        <EditorPanel
            title="Ask me"
            icon={
                <MessageCircleQuestion
                    className="size-3.5 text-brand"
                    aria-hidden
                />
            }
            actions={
                <div className="flex items-center gap-0.5">
                    {messages.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            disabled={busy || clear.isPending}
                            onClick={onClear}
                            aria-label="Clear the chat"
                            title="Clear the chat"
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={onClose}
                        aria-label="Close"
                        title="Close (Esc)"
                    >
                        <X className="size-3.5" />
                    </Button>
                </div>
            }
        >
            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto p-3">
                {chat.isPending ? (
                    <div className="flex flex-1 items-center justify-center">
                        <Spinner />
                    </div>
                ) : chat.isError ? (
                    <p className="text-sm text-destructive">
                        {getErrorMessage(
                            chat.error,
                            'The chat could not be loaded.',
                        )}
                    </p>
                ) : messages.length === 0 ? (
                    <ChatSuggestions
                        onRecordPage={pageRecordId !== null}
                        disabled={busy}
                        onPick={(question) =>
                            onAsk(question).catch(() => undefined)
                        }
                    />
                ) : (
                    <>
                        {chat.hasNextPage && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="mb-2 self-center text-xs"
                                disabled={chat.isFetchingNextPage}
                                onClick={() => void chat.fetchNextPage()}
                            >
                                {chat.isFetchingNextPage && <Spinner />}
                                Earlier messages
                            </Button>
                        )}
                        <ul className="flex flex-col gap-3" aria-live="polite">
                            {messages.map((message) => (
                                <ChatMessage
                                    key={message.id}
                                    message={message}
                                />
                            ))}
                        </ul>
                        {pending && (
                            <p className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                <Spinner />
                                Looking it up in the archive…
                            </p>
                        )}
                    </>
                )}
                <div ref={end} />
            </div>
            <ChatComposer busy={busy} active={active} onAsk={onAsk} />
        </EditorPanel>
    );
}
