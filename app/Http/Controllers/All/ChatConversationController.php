<?php

namespace App\Http\Controllers\All;

use App\Enums\ChatMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\All\DestroyChatConversationRequest;
use App\Http\Requests\All\IndexChatConversationsRequest;
use App\Http\Requests\All\ShowChatConversationRequest;
use App\Jobs\AnswerChatQuestionJob;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The saved "Ask me" conversations of the signed-in person: the history
 * list, one conversation's messages, and deleting one. Nobody sees anyone
 * else's conversations (ChatConversationPolicy).
 */
class ChatConversationController extends Controller
{
    public const int PAGE_SIZE = 20;

    public const int MESSAGES_PAGE_SIZE = 30;

    /**
     * The asker's conversations, most recently active first.
     */
    public function index(IndexChatConversationsRequest $request): JsonResponse
    {
        $conversations = ChatConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->simplePaginate(self::PAGE_SIZE, ['id', 'title', 'updated_at']);

        return response()->json([
            'conversations' => $conversations->getCollection()
                ->map(fn (ChatConversation $conversation): array => $conversation->present())
                ->all(),
            'next_page' => $conversations->hasMorePages() ? $conversations->currentPage() + 1 : null,
        ]);
    }

    /**
     * A page of one conversation, oldest first.
     */
    public function show(ShowChatConversationRequest $request, ChatConversation $conversation): JsonResponse
    {
        $before = $request->validated('before');
        $messages = $conversation->messages()
            ->when($before !== null, fn ($query) => $query->where('id', '<', (int) $before))
            ->latest('id')
            ->limit(self::MESSAGES_PAGE_SIZE + 1)
            ->get(['id', 'chat_conversation_id', 'record_id', 'user_id', 'role', 'content', 'status', 'citations', 'link', 'tools', 'follow_ups', 'created_at']);
        $page = $messages->take(self::MESSAGES_PAGE_SIZE)->reverse()->values();

        return response()->json([
            'conversation' => $conversation->present(),
            'messages' => $page->map(fn (ChatMessage $message): array => $message->present())->all(),
            'has_more' => $messages->count() > self::MESSAGES_PAGE_SIZE,
            'pending' => $page->contains(fn (ChatMessage $message): bool => $message->status === ChatMessageStatus::Pending),
        ]);
    }

    /**
     * Delete one conversation (not while its question is being answered).
     */
    public function destroy(DestroyChatConversationRequest $request, ChatConversation $conversation): Response
    {
        abort_if(
            Cache::has(AnswerChatQuestionJob::lockKey($conversation->user_id))
                && $conversation->messages()->where('status', ChatMessageStatus::Pending)->exists(),
            409,
            'Wait for the answer before deleting this chat.',
        );

        $conversation->delete();

        return response()->noContent();
    }
}
