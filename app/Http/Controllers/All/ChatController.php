<?php

namespace App\Http\Controllers\All;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\All\AskQuestionRequest;
use App\Http\Requests\All\ClearChatRequest;
use App\Jobs\AnswerChatQuestionJob;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * "Ask me" (Phase 7): the floating assistant on every page, for every
 * signed-in role. Each account keeps a history of conversations
 * (ChatConversationController lists, reads and deletes them). Answers are
 * written in the background (AnswerChatQuestionJob) only from approved
 * records the account may see, through the function tools in
 * App\Http\Controllers\FunctionTools.
 */
class ChatController extends Controller
{
    /**
     * Ask a question in a saved conversation, or start a new one; the answer
     * arrives later (`chat.answered` on the asker's own channel).
     */
    public function store(AskQuestionRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversationId = $request->validated('conversation_id');
        $conversation = null;

        if ($conversationId !== null) {
            $conversation = ChatConversation::query()->findOrFail((int) $conversationId);
            Gate::authorize('update', $conversation);
        }

        abort_unless(
            Cache::add(AnswerChatQuestionJob::lockKey($user->id), true, AnswerChatQuestionJob::LOCK_SECONDS),
            409,
            'The previous question is still being answered.',
        );

        $content = $request->validated('question');
        $recordId = $request->validated('record_id');

        [$conversation, $question] = DB::transaction(function () use ($conversation, $user, $content, $recordId): array {
            if ($conversation === null) {
                $conversation = new ChatConversation(['title' => ChatConversation::titleFor($content)]);
                $conversation->user_id = $user->id;
                $conversation->save();
            } else {
                $conversation->touch();
            }

            $question = new ChatMessage([
                'role' => ChatRole::User,
                'content' => $content,
            ]);
            // Only a hint: ArchiveChat uses it only if the account may ask about that record.
            $question->record_id = is_string($recordId) ? strtolower($recordId) : null;
            $question->user_id = $user->id;
            $question->chat_conversation_id = $conversation->id;
            $question->status = ChatMessageStatus::Pending;
            $question->save();

            return [$conversation, $question];
        });

        AnswerChatQuestionJob::dispatch($question);

        return response()->json([
            'message' => $question->present(),
            'conversation' => $conversation->present(),
        ], 202);
    }

    /**
     * Clear the asker's whole history (not while a question is being answered).
     */
    public function destroy(ClearChatRequest $request): Response
    {
        $userId = $request->user()->id;

        abort_if(Cache::has(AnswerChatQuestionJob::lockKey($userId)), 409, 'Wait for the answer before clearing the chat history.');

        DB::transaction(function () use ($userId): void {
            ChatMessage::query()->where('user_id', $userId)->delete();
            ChatConversation::query()->where('user_id', $userId)->delete();
        });

        return response()->noContent();
    }
}
