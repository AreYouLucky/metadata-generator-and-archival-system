import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    useInfiniteQuery,
    useMutation,
    useQueryClient,
} from '@tanstack/react-query';
import type { InfiniteData } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { clear, destroy, index, show, store } from '@/routes/chat';
import type {
    ChatConversation,
    ChatConversationsPage,
    ChatMessage,
    ChatPage,
} from '@/types/chat';

/** Everything "Ask me" caches (history and conversations). */
export const chatKey = ['chat'] as const;
export const conversationsKey = ['chat', 'conversations'] as const;
export const conversationKey = (conversationId: number) =>
    ['chat', 'conversation', conversationId] as const;

type ConversationData = InfiniteData<ChatPage, number | null>;

/** The signed-in person's saved conversations, most recently active first. */
export function useConversations(enabled: boolean) {
    return useInfiniteQuery({
        queryKey: conversationsKey,
        queryFn: async ({ pageParam }) =>
            (
                await http.get<ChatConversationsPage>(
                    index.url({
                        query: pageParam > 1 ? { page: pageParam } : {},
                    }),
                )
            ).data,
        initialPageParam: 1,
        getNextPageParam: (page) => page.next_page,
        enabled,
        staleTime: 60_000,
    });
}

/**
 * One saved conversation, newest page first (`before` pages back). While a
 * question waits for its answer it is re-read every few seconds, in case
 * the Reverb event is missed. Null is a new chat: nothing to load.
 */
export function useChat(conversationId: number | null) {
    return useInfiniteQuery({
        queryKey: conversationKey(conversationId ?? 0),
        queryFn: async ({ pageParam }) =>
            (
                await http.get<ChatPage>(
                    show.url(conversationId ?? 0, {
                        query: pageParam ? { before: pageParam } : {},
                    }),
                )
            ).data,
        initialPageParam: null as number | null,
        getNextPageParam: (page) =>
            page.has_more ? (page.messages[0]?.id ?? null) : null,
        enabled: conversationId !== null,
        staleTime: 60_000,
        refetchInterval: (query) =>
            query.state.data?.pages[0]?.pending ? 5_000 : false,
    });
}

/**
 * Ask a question in a saved conversation, or start a new one (null), with
 * the record whose page the person is on (a hint the server checks). It
 * shows at once as pending; the answer arrives later.
 */
export function useAskQuestion() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: {
            question: string;
            recordId: string | null;
            conversationId: number | null;
        }) =>
            (
                await http.post<{
                    message: ChatMessage;
                    conversation: ChatConversation;
                }>(store.url(), {
                    question: input.question,
                    record_id: input.recordId,
                    conversation_id: input.conversationId,
                })
            ).data,
        onSuccess: ({ message, conversation }) => {
            queryClient.setQueryData<ConversationData>(
                conversationKey(conversation.id),
                (data) => {
                    if (!data) {
                        return {
                            pages: [
                                {
                                    conversation,
                                    messages: [message],
                                    has_more: false,
                                    pending: true,
                                },
                            ],
                            pageParams: [null],
                        };
                    }

                    const [newest, ...older] = data.pages;

                    return {
                        ...data,
                        pages: [
                            {
                                ...newest,
                                messages: [...newest.messages, message],
                                pending: true,
                            },
                            ...older,
                        ],
                    };
                },
            );
            void queryClient.invalidateQueries({ queryKey: conversationsKey });
        },
    });
}

/** Delete one saved conversation. */
export function useDeleteConversation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (conversationId: number) => {
            await http.delete(destroy.url(conversationId));
        },
        onSuccess: (_, conversationId) => {
            queryClient.removeQueries({
                queryKey: conversationKey(conversationId),
            });
            void queryClient.invalidateQueries({ queryKey: conversationsKey });
        },
    });
}

/** Clear the signed-in person's whole history. */
export function useClearChat() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            await http.delete(clear.url());
        },
        onSuccess: () => queryClient.resetQueries({ queryKey: chatKey }),
    });
}

/**
 * Re-read the conversations when an answer is ready. The event comes on the
 * person's own private channel, so nobody else learns of the question.
 */
export function useChatUpdates(userId: number) {
    const queryClient = useQueryClient();

    useEcho(
        `App.Models.User.${userId}`,
        '.chat.answered',
        () => void queryClient.invalidateQueries({ queryKey: chatKey }),
        [userId],
    );
}

/** The signed-in account, or null for a guest. */
export function useChatUser(): { id: number; role: string } | null {
    const user = usePage().props.auth.user;

    return user ? { id: user.id, role: user.role } : null;
}
