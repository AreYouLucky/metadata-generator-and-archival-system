<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;

/**
 * "Ask me" conversations are private: only the active account that had the
 * conversation may read it, continue it or delete it (admins included).
 */
class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): bool
    {
        return $user->is_active && $conversation->user_id === $user->id;
    }

    public function update(User $user, ChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, ChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
