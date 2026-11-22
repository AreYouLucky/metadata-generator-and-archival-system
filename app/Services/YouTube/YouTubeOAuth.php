<?php

namespace App\Services\YouTube;

use App\Models\User;
use App\Models\YoutubeAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Google OAuth 2.0 for YouTube (web server flow, offline access): the consent
 * URL, exchanging the code, refreshing the access token and revoking it on
 * disconnect. Only `youtube.upload` (publish, set thumbnails) and
 * `youtube.readonly` (channel name, video status) are asked for. Plain HTTP
 * calls; no Google SDK (see DECISIONS.md).
 */
class YouTubeOAuth
{
    public const string AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const string REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public const array SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
    ];

    public function __construct(private YouTubeClient $client) {}

    public function isConfigured(): bool
    {
        return filled(config('services.youtube.client_id')) && filled(config('services.youtube.client_secret'));
    }

    public function redirectUri(): string
    {
        $configured = config('services.youtube.redirect_uri');

        return is_string($configured) && $configured !== '' ? $configured : route('youtube.callback');
    }

    /**
     * Google's consent screen. `prompt=consent` makes Google send a refresh
     * token every time, so reconnecting always works.
     */
    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.youtube.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Swap the callback's code for tokens, look up the channel, and save it as
     * the user's (replacing any channel connected before).
     */
    public function connect(User $user, string $code): YoutubeAccount
    {
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ], 'connect YouTube');

        $refreshToken = $tokens['refresh_token'] ?? null;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new YouTubeException('Google did not grant offline access. Try connecting again.');
        }

        $granted = explode(' ', (string) ($tokens['scope'] ?? ''));

        if (array_diff(self::SCOPES, $granted) !== []) {
            throw new YouTubeException('Allow both YouTube permissions on Google\'s consent screen (upload videos and view your channel).');
        }

        $channel = $this->client->myChannel((string) $tokens['access_token']);

        return DB::transaction(function () use ($user, $tokens, $refreshToken, $channel): YoutubeAccount {
            $account = $user->youtubeAccount()->firstOrNew();
            $account->channel_id = $channel['id'];
            $account->channel_title = $channel['title'];
            $account->channel_thumbnail_url = $channel['thumbnail_url'];
            $account->access_token = (string) $tokens['access_token'];
            $account->refresh_token = $refreshToken;
            $account->token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
            $account->scopes = (string) ($tokens['scope'] ?? '');
            $account->save();

            return $account;
        });
    }

    /**
     * A valid access token, refreshed when it is about to expire. One refresh
     * at a time per channel, so parallel jobs don't race.
     */
    public function accessToken(YoutubeAccount $account): string
    {
        if (! $account->tokenExpired()) {
            return $account->access_token;
        }

        return Cache::lock("youtube-token:{$account->id}", 30)->block(15, function () use ($account): string {
            $account->refresh();

            if (! $account->tokenExpired()) {
                return $account->access_token;
            }

            $tokens = $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ], 'refresh the YouTube sign-in');

            $account->access_token = (string) $tokens['access_token'];
            $account->token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
            $account->save();

            return $account->access_token;
        });
    }

    /**
     * Revoke the grant at Google (best effort: a token that is already
     * invalid is fine) and forget the channel.
     */
    public function disconnect(YoutubeAccount $account): void
    {
        try {
            Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $account->refresh_token]);
        } catch (ConnectionException) {
            // The local copy is removed anyway; the grant can be removed in the Google account.
        }

        $account->delete();
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array<string, mixed>
     */
    private function tokenRequest(array $parameters, string $action): array
    {
        try {
            $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                ...$parameters,
                'client_id' => config('services.youtube.client_id'),
                'client_secret' => config('services.youtube.client_secret'),
            ]);
        } catch (ConnectionException) {
            throw new YouTubeException("Could not reach Google to {$action}.", transient: true);
        }

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw YouTubeException::fromResponse($response, $action);
        }

        /** @var array<string, mixed> */
        return $response->json();
    }
}
