<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A URL the AI service may download a video from (yt-dlp): http or https on
 * the standard port, no credentials, and a public host name, never
 * localhost, a private or reserved IP address, or an internal-only name.
 * The AI service checks again after resolving the name (and on every
 * redirect), since a public name can still point inside the network.
 */
class PublicMediaUrl implements ValidationRule
{
    public const int MAX_LENGTH = 2048;

    /**
     * Name endings that only resolve inside a network.
     */
    private const array INTERNAL_SUFFIXES = ['localhost', '.local', '.internal', '.lan', '.home', '.corp', '.intranet'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > self::MAX_LENGTH || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('Enter a valid web address (http or https).');

            return;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Only http and https addresses can be imported.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('The address must not contain a user name or password.');

            return;
        }

        if (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            $fail('Only addresses on the standard web ports can be imported.');

            return;
        }

        if (! self::isPublicHost($host)) {
            $fail('That address points to a local or private network, which cannot be imported.');
        }
    }

    public static function isPublicHost(string $host): bool
    {
        if ($host === '' || ! str_contains($host, '.')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return true;
    }
}
