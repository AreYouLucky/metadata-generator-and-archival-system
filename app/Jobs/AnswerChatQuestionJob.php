<?php

namespace App\Jobs;

use App\Enums\ChatMessageStatus;
use App\Events\ChatAnswered;
use App\Models\ChatMessage;
use App\Services\Chat\ArchiveChat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Answers an "Ask me" question (Phase 7) in the background: the tool loop
 * makes several AI calls. The reply is stored, then the asker's private
 * channel is told to reload the conversation.
 */
class AnswerChatQuestionJob implements ShouldQueue
{
    use Queueable;

    public const int LOCK_SECONDS = 300;

    public int $tries = 1;

    public int $timeout = 240;

    /** A conversation cleared while this waited leaves nothing to answer. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public ChatMessage $question) {}

    /**
     * One question at a time per account.
     */
    public static function lockKey(int $userId): string
    {
        return "chat:{$userId}";
    }

    public function handle(ArchiveChat $chat): void
    {
        try {
            $question = $this->question->fresh(['user']);

            if ($question === null || $question->status !== ChatMessageStatus::Pending) {
                return;
            }

            if (! $question->user->is_active) {
                $chat->fail($question, 'This account can no longer ask questions.');

                return;
            }

            $chat->answer($question);
        } finally {
            $this->finish();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Chat question failed', ['user_id' => $this->question->user_id, 'error' => $exception?->getMessage()]);
        $question = $this->question->fresh();

        if ($question?->status === ChatMessageStatus::Pending) {
            rescue(fn () => app(ArchiveChat::class)->fail($question, 'The question could not be answered. Try again.'), report: false);
        }

        $this->finish();
    }

    private function finish(): void
    {
        Cache::forget(self::lockKey($this->question->user_id));
        rescue(fn () => ChatAnswered::dispatch($this->question->user_id), report: false);
    }
}
