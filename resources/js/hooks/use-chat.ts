import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    useInfiniteQuery,
    useMutation,
    useQueryClient,
} from '@tanstack/react-query';
import type { InfiniteData } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy, index, store } from '@/routes/chat';
import type { ChatMessage, ChatPage } from '@/types/chat';

export const chatKey = ['chat'] as const;

/**
 * The signed-in person's "Ask me" conversation, newest page first (`before`
 * pages back). While a question waits for its answer the conversation is
 * re-read every few seconds, in case the Reverb event is missed.
 */
export function useChat(enabled: boolean) {
    return useInfiniteQuery({
        queryKey: chatKey,
        queryFn: async ({ pageParam }) =>
            (
                await http.get<ChatPage>(
                    index.url({
                        query: pageParam ? { before: pageParam } : {},
                    }),
                )
            ).data,
        initialPageParam: null as number | null,
        getNextPageParam: (page) =>
            page.has_more ? (page.messages[0]?.id ?? null) : null,
        enabled,
        staleTime: 60_000,
        refetchInterval: (query) =>
            query.state.data?.pages[0]?.pending ? 5_000 : false,
    });
}

/**
 * Ask a question, with the record whose page the person is on (a hint the
 * server checks). It shows at once as pending; the answer arrives later.
 */
export function useAskQuestion() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: {
            question: string;
            recordId: string | null;
        }) =>
            (
                await http.post<{ message: ChatMessage }>(store.url(), {
                    question: input.question,
                    record_id: input.recordId,
                })
            ).data.message,
        onSuccess: (message) => {
            queryClient.setQueryData<InfiniteData<ChatPage, number | null>>(
                chatKey,
                (data) => {
                    if (!data) {
                        return data;
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
        },
    });
}

/** Clear the signed-in person's conversation. */
export function useClearChat() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            await http.delete(destroy.url());
        },
        onSuccess: () => queryClient.resetQueries({ queryKey: chatKey }),
    });
}

/**
 * Re-read the conversation when an answer is ready. The event comes on the
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
