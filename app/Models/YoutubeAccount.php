<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Database\Factories\YoutubeAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's connected YouTube channel (Google OAuth, Phase 7). The tokens are
 * encrypted at rest and never leave the server; every field is set by the
 * OAuth callback, never mass-assigned.
 *
 * @property int $id
 * @property int $user_id
 * @property string $channel_id
 * @property string $channel_title
 * @property string|null $channel_thumbnail_url
 * @property string $access_token
 * @property string $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['access_token', 'refresh_token'])]
class YoutubeAccount extends Model
{
    /** @use HasFactory<YoutubeAccountFactory> */
    use HasFactory;

    /**
     * Nothing is mass-assignable: the OAuth callback sets each field.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channelUrl(): string
    {
        return 'https://www.youtube.com/channel/'.rawurlencode($this->channel_id);
    }

    /**
     * Whether the access token must be refreshed before use (a minute early).
     */
    public function tokenExpired(): bool
    {
        return $this->token_expires_at === null || $this->token_expires_at->subMinute()->isPast();
    }
}
