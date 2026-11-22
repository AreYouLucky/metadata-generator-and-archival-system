<?php

namespace App\Http\Controllers\All;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\All\AskQuestionRequest;
use App\Http\Requests\All\ClearChatRequest;
use App\Http\Requests\All\IndexChatMessagesRequest;
use App\Jobs\AnswerChatQuestionJob;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * "Ask me" (Phase 7): the floating assistant on every page, for every
 * signed-in role. One conversation per account. Answers are written in the
 * background (AnswerChatQuestionJob) only from approved records the account
 * may see, through the function tools in App\Http\Controllers\FunctionTools.
 */
class ChatController extends Controller
{
    public const int PAGE_SIZE = 30;

    /**
     * A page of the asker's conversation, oldest first.
     */
    public function index(IndexChatMessagesRequest $request): JsonResponse
    {
        $before = $request->validated('before');
        $messages = ChatMessage::query()
            ->where('user_id', $request->user()->id)
            ->when($before !== null, fn ($query) => $query->where('id', '<', (int) $before))
            ->latest('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get(['id', 'record_id', 'user_id', 'role', 'content', 'status', 'citations', 'tools', 'created_at']);
        $page = $messages->take(self::PAGE_SIZE)->reverse()->values();

        return response()->json([
            'messages' => $page->map(fn (ChatMessage $message): array => $message->present())->all(),
            'has_more' => $messages->count() > self::PAGE_SIZE,
            'pending' => $page->contains(fn (ChatMessage $message): bool => $message->status === ChatMessageStatus::Pending),
        ]);
    }

    /**
     * Ask a question; the answer arrives later (`record.chat-answered` on
     * the asker's own channel).
     */
    public function store(AskQuestionRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        abort_unless(
            Cache::add(AnswerChatQuestionJob::lockKey($userId), true, AnswerChatQuestionJob::LOCK_SECONDS),
            409,
            'The previous question is still being answered.',
        );

        $recordId = $request->validated('record_id');
        $question = new ChatMessage([
            'role' => ChatRole::User,
            'content' => $request->validated('question'),
        ]);
        // Only a hint: ArchiveChat uses it only if the account may ask about that record.
        $question->record_id = is_string($recordId) ? strtolower($recordId) : null;
        $question->user_id = $userId;
        $question->status = ChatMessageStatus::Pending;
        $question->save();

        AnswerChatQuestionJob::dispatch($question);

        return response()->json(['message' => $question->present()], 202);
    }

    /**
     * Clear the asker's conversation (not while a question is being answered).
     */
    public function destroy(ClearChatRequest $request): Response
    {
        $userId = $request->user()->id;

        abort_if(Cache::has(AnswerChatQuestionJob::lockKey($userId)), 409, 'Wait for the answer before clearing the chat.');

        ChatMessage::query()->where('user_id', $userId)->delete();

        return response()->noContent();
    }
}
