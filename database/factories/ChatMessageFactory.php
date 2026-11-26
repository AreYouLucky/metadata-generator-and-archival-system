<?php

namespace Database\Factories;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatRole;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * An answered question by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The page the question was asked on; none by default.
            'record_id' => null,
            'user_id' => User::factory()->encoder(),
            // A conversation of the same person.
            'chat_conversation_id' => fn (array $attributes): int => ChatConversation::factory()->create(['user_id' => $attributes['user_id']])->id,
            'role' => ChatRole::User,
            'content' => fake()->sentence().'?',
            'status' => ChatMessageStatus::Answered,
            'citations' => null,
            'tools' => null,
            'follow_ups' => null,
        ];
    }

    public function in(ChatConversation $conversation): static
    {
        return $this->state(['chat_conversation_id' => $conversation->id, 'user_id' => $conversation->user_id]);
    }

    public function pending(): static
    {
        return $this->state(['status' => ChatMessageStatus::Pending]);
    }

    public function answer(ChatMessageStatus $status = ChatMessageStatus::Answered): static
    {
        return $this->state([
            'role' => ChatRole::Assistant,
            'content' => fake()->sentence(),
            'status' => $status,
        ]);
    }
}
