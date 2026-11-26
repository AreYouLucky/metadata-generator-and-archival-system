<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One saved "Ask me" conversation. It is titled after its first question and
 * `updated_at` moves with every new message, so the history lists the most
 * recent first. The owner is set explicitly, never mass-assigned.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title'])]
class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    public const int TITLE_LENGTH = 120;

    /**
     * A title from the first question: one line, at most TITLE_LENGTH characters.
     */
    public static function titleFor(string $question): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $question));

        return Str::limit($line, self::TITLE_LENGTH - 3);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * The fields the history list shows.
     *
     * @return array{id: int, title: string, updated_at: string|null}
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
