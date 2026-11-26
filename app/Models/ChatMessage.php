<?php

namespace App\Models;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatRole;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question or answer in one of a person's saved "Ask me" conversations
 * (Phase 7). `record_id` is the page the question was asked on, if any. The
 * owner, the conversation and the status are set explicitly, never
 * mass-assigned. `follow_ups` are questions an answer suggests asking next.
 *
 * @property int $id
 * @property int|null $chat_conversation_id
 * @property string|null $record_id
 * @property int $user_id
 * @property ChatRole $role
 * @property string $content
 * @property ChatMessageStatus $status
 * @property list<array{record_id: string, title: string, start: float, end: float, url: string}>|null $citations
 * @property list<string>|null $tools
 * @property list<string>|null $follow_ups
 * @property array{record_id: string, title: string, url: string}|null $link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['role', 'content', 'citations', 'link', 'tools', 'follow_ups'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ChatRole::class,
            'status' => ChatMessageStatus::class,
            'citations' => 'array',
            'tools' => 'array',
            'follow_ups' => 'array',
            'link' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The fields the chat panel shows.
     *
     * @return array{id: int, conversation_id: int|null, record_id: string|null, role: string, content: string, status: string, citations: list<array{record_id: string, title: string, start: float, end: float, url: string}>, link: array{record_id: string, title: string, url: string}|null, tools: list<string>, follow_ups: list<string>, created_at: string|null}
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->chat_conversation_id,
            'record_id' => $this->record_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'status' => $this->status->value,
            'citations' => $this->citations ?? [],
            'link' => $this->link,
            'tools' => $this->tools ?? [],
            'follow_ups' => $this->follow_ups ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
