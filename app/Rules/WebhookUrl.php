<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Where a public-API webhook may be sent: https on the standard port, no
 * credentials in the address, and a public host (never localhost, a private
 * or reserved IP, or an internal-only name). The sender resolves the name
 * again and pins the address it checked (SendRecordWebhookJob), since a
 * public name can still point inside the network.
 */
class WebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > PublicMediaUrl::MAX_LENGTH || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('Enter a valid https address for the webhook.');

            return;
        }

        $parts = parse_url($value);

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            $fail('The webhook address must use https.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('The webhook address must not contain a user name or password.');

            return;
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            $fail('The webhook address must use the standard https port.');

            return;
        }

        if (! PublicMediaUrl::isPublicHost(strtolower(trim((string) ($parts['host'] ?? ''), '[]')))) {
            $fail('The webhook address points to a local or private network.');
        }
    }
}
