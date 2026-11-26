import { History, SquarePen, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import EditorPanel from "@/components/encoder/editor-panel";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import {
    useAskQuestion,
    useChat,
    useChatUpdates,
    useConversations,
} from "@/hooks/use-chat";
import { getErrorMessage } from "@/lib/utilities";
import { cn } from "@/lib/utils";
import { useAskMeStore } from "@/stores/ask-me-store";
import AskMeMascot from "../ask-me-mascot";
import ChatComposer from "./chat-composer";
import ChatFollowUps from "./chat-follow-ups";
import ChatHistory from "./chat-history";
import ChatMessage from "./chat-message";
import ChatSuggestions from "./chat-suggestions";

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
 * video" means that record. Conversations are saved: the history lists
 * them to carry on, and a new chat starts fresh.
 */
export default function ChatPanel({ userId, active, onClose }: Props) {
    const storedId = useAskMeStore((state) => state.conversationId);
    const setConversationId = useAskMeStore((state) => state.setConversationId);
    const pageRecordId = useAskMeStore((state) => state.page?.recordId ?? null);
    const [view, setView] = useState<"chat" | "history">("chat");
    const conversationId = storedId ?? null;
    const history = useConversations(storedId === undefined);
    const chat = useChat(conversationId);
    const ask = useAskQuestion();
    useChatUpdates(userId);

    // The first time, carry on with the most recent conversation.
    useEffect(() => {
        if (storedId === undefined && history.isSuccess) {
            setConversationId(
                history.data.pages[0]?.conversations[0]?.id ?? null,
            );
        }
    }, [storedId, history.isSuccess, history.data, setConversationId]);

    // Pages arrive newest first; each page is oldest first.
    const messages = useMemo(
        () =>
            conversationId === null
                ? []
                : [...(chat.data?.pages ?? [])]
                      .reverse()
                      .flatMap((page) => page.messages),
        [chat.data, conversationId],
    );
    const pending =
        conversationId !== null && (chat.data?.pages[0]?.pending ?? false);
    const busy = pending || ask.isPending;
    const title = chat.data?.pages[0]?.conversation.title;
    const resolving = storedId === undefined && history.isPending;
    const last = messages.at(-1);

    // Follow the conversation as it grows, and when the window reopens.
    const end = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (active && view === "chat") {
            end.current?.scrollIntoView({ block: "end" });
        }
    }, [last?.id, pending, active, view]);

    const onAsk = (question: string) =>
        ask
            .mutateAsync({ question, recordId: pageRecordId, conversationId })
            .then(({ conversation }) => {
                setConversationId(conversation.id);
                setView("chat");
            })
            .catch((error: unknown) => {
                toast.error(
                    getErrorMessage(error, "The question could not be sent."),
                );
                throw error;
            });

    const askQuietly = (question: string) =>
        void onAsk(question).catch(() => undefined);

    const startNewChat = () => {
        setConversationId(null);
        setView("chat");
    };

    /** The question a failed answer replied to. */
    const questionBefore = (index: number) =>
        messages.slice(0, index).findLast((message) => message.role === "user")
            ?.content;

    const iconButton = "size-8 text-muted-foreground hover:text-foreground";

    return (
        <EditorPanel
            title="Ask me"
            className="bg-card"
            heading={
                <h2 className="flex min-w-0 items-center gap-2 py-4">
                    <AskMeMascot waving={false} className="-my-3 size-10" />
                    <span className="grid min-w-0 leading-tight">
                        <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            Ask me
                        </span>
                        {view === "history" ? (
                            <span className="truncate text-xs font-medium">
                                Chat history
                            </span>
                        ) : (
                            (title || conversationId === null) && (
                                <span className="truncate text-xs font-medium">
                                    {conversationId === null
                                        ? "New chat"
                                        : title}
                                </span>
                            )
                        )}
                    </span>
                </h2>
            }
            actions={
                <div className="flex shrink-0 items-center gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={iconButton}
                        disabled={conversationId === null && view === "chat"}
                        onClick={startNewChat}
                        aria-label="New chat"
                        title="New chat"
                    >
                        <SquarePen className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={cn(
                            iconButton,
                            view === "history" && "bg-muted text-foreground",
                        )}
                        onClick={() =>
                            setView(view === "history" ? "chat" : "history")
                        }
                        aria-pressed={view === "history"}
                        aria-label="Chat history"
                        title="Chat history"
                    >
                        <History className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={iconButton}
                        onClick={onClose}
                        aria-label="Close"
                        title="Close (Esc)"
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            }
        >
            {view === "history" ? (
                <ChatHistory
                    activeId={conversationId}
                    busy={busy}
                    onOpen={(id) => {
                        setConversationId(id);
                        setView("chat");
                    }}
                    onNewChat={startNewChat}
                    onDeleted={(id) => {
                        if (id === null || id === conversationId) {
                            setConversationId(null);
                        }
                    }}
                />
            ) : (
                <>
                    <div className="flex min-h-0 flex-1 flex-col overflow-y-auto pl-8 pr-4 py-5">
                        {resolving ||
                        (conversationId !== null && chat.isPending) ? (
                            <div className="flex flex-1 items-center justify-center">
                                <Spinner />
                            </div>
                        ) : conversationId !== null && chat.isError ? (
                            <div className="grid justify-items-start gap-3 text-sm">
                                <p className="text-destructive">
                                    {getErrorMessage(
                                        chat.error,
                                        "The chat could not be loaded.",
                                    )}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={startNewChat}
                                >
                                    Start a new chat
                                </Button>
                            </div>
                        ) : messages.length === 0 ? (
                            <ChatSuggestions
                                onRecordPage={pageRecordId !== null}
                                disabled={busy}
                                onPick={askQuietly}
                            />
                        ) : (
                            <>
                                {chat.hasNextPage && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="mb-5 self-center text-xs"
                                        disabled={chat.isFetchingNextPage}
                                        onClick={() =>
                                            void chat.fetchNextPage()
                                        }
                                    >
                                        {chat.isFetchingNextPage && <Spinner />}
                                        Earlier messages
                                    </Button>
                                )}
                                <ul
                                    className="flex flex-col gap-6"
                                    aria-live="polite"
                                >
                                    {messages.map((message, index) => {
                                        const retry =
                                            message.status === "failed"
                                                ? questionBefore(index)
                                                : undefined;

                                        return (
                                            <ChatMessage
                                                key={message.id}
                                                message={message}
                                                retryDisabled={busy}
                                                onRetry={
                                                    retry
                                                        ? () =>
                                                              askQuietly(retry)
                                                        : undefined
                                                }
                                            />
                                        );
                                    })}
                                </ul>
                                {pending ? (
                                    <p className="mt-6 flex items-center gap-2 pl-1 text-xs text-muted-foreground">
                                        <Spinner />
                                        Looking it up in the archive…
                                    </p>
                                ) : (
                                    last?.role === "assistant" &&
                                    last.follow_ups.length > 0 && (
                                        <ChatFollowUps
                                            questions={last.follow_ups}
                                            disabled={busy}
                                            onPick={askQuietly}
                                        />
                                    )
                                )}
                            </>
                        )}
                        <div ref={end} />
                    </div>
                </>
            )}
            {/* Stays mounted in the history, so a half-typed question survives. */}
            <div hidden={view === "history"}>
                <ChatComposer
                    busy={busy}
                    active={active && view === "chat"}
                    onAsk={onAsk}
                />
            </div>
        </EditorPanel>
    );
}
