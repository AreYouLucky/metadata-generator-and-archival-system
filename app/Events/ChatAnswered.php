<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An "Ask me" question got its reply (or failed). Sent on the asker's own
 * channel only, without the content: the chat re-reads the conversation
 * through the authorised GET route.
 */
class ChatAnswered implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $userId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.answered';
    }

    /**
     * @return array<string, never>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
