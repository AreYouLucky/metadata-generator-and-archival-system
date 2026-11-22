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
 * One question or answer in a person's "Ask me" conversation (Phase 7).
 * `record_id` is the page the question was asked on, if any. The owner and
 * the status are set explicitly, never mass-assigned.
 *
 * @property int $id
 * @property string|null $record_id
 * @property int $user_id
 * @property ChatRole $role
 * @property string $content
 * @property ChatMessageStatus $status
 * @property list<array{record_id: string, title: string, start: float, end: float, url: string}>|null $citations
 * @property list<string>|null $tools
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['role', 'content', 'citations', 'tools'])]
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The fields the chat panel shows.
     *
     * @return array{id: int, role: string, content: string, status: string, citations: list<array{record_id: string, title: string, start: float, end: float, url: string}>, tools: list<string>, created_at: string|null}
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'status' => $this->status->value,
            'citations' => $this->citations ?? [],
            'tools' => $this->tools ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
