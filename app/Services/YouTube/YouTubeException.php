<?php

namespace App\Services\YouTube;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A YouTube or Google OAuth call failed. `transient` errors (5xx, timeouts,
 * rate limits) are worth retrying; the rest need the encoder to act
 * (reconnect the channel, fix the title, wait for quota).
 */
class YouTubeException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $transient = false, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }

    /**
     * Google's error body: `{error: {message, errors: [{reason}]}}` for the
     * Data API, `{error, error_description}` for OAuth.
     */
    public static function fromResponse(Response $response, string $action): self
    {
        $error = $response->json('error');
        $reason = is_array($error) ? $response->json('error.errors.0.reason') : (is_string($error) ? $error : null);
        $detail = is_array($error) ? $response->json('error.message') : $response->json('error_description');
        $status = $response->status();
        $transient = $status >= 500 || $status === 429 || in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'backendError'], true);

        $message = match (true) {
            $reason === 'invalid_grant' => 'YouTube access was revoked or expired. Reconnect the channel in Settings → YouTube.',
            $reason === 'quotaExceeded' => 'The YouTube API quota for today is used up. Try again tomorrow.',
            $reason === 'uploadLimitExceeded' => 'This channel has reached its YouTube upload limit for now.',
            $reason === 'youtubeSignupRequired' => 'The Google account has no YouTube channel yet. Create one on YouTube, then connect again.',
            in_array($reason, ['invalidTitle', 'invalidDescription', 'invalidTags'], true) => 'YouTube refused the '.strtolower(substr((string) $reason, 7)).': '.(is_string($detail) ? strip_tags($detail) : 'invalid value').'.',
            default => "Could not {$action} (YouTube answered {$status}".(is_string($detail) && $detail !== '' ? ': '.strip_tags($detail) : '').').',
        };

        return new self(mb_substr($message, 0, 900), $transient, is_string($reason) ? $reason : null);
    }
}
