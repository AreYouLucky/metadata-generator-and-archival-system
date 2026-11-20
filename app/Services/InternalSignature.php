<?php

namespace App\Services;

use Illuminate\Container\Attributes\Config;
use RuntimeException;

/**
 * Signs and verifies requests between Laravel and the Python AI service.
 *
 * Scheme (identical in ai-service/app/security.py):
 *   X-Timestamp: unix seconds
 *   X-Signature: sha256=hex(HMAC_SHA256(secret, timestamp + "." + raw_body))
 *
 * Verification fails closed: a missing secret, malformed header, or a timestamp
 * outside the allowed window is always rejected.
 */
class InternalSignature
{
    public const string TIMESTAMP_HEADER = 'X-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Signature';

    public function __construct(
        #[Config('archive.internal.shared_secret')] private ?string $secret,
        #[Config('archive.internal.signature_max_age_seconds')] private int $maxAgeSeconds = 300,
    ) {}

    /**
     * The `sha256=<hex>` signature for a timestamp and raw body.
     */
    public function sign(string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $this->secret());
    }

    /**
     * Headers to attach to an outgoing request with this raw body.
     *
     * @return array{X-Timestamp: string, X-Signature: string}
     */
    public function headers(string $body, ?int $now = null): array
    {
        $timestamp = (string) ($now ?? time());

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => $this->sign($timestamp, $body),
        ];
    }

    /**
     * Whether the headers are a valid, fresh signature of the raw body.
     */
    public function verify(?string $timestamp, ?string $signature, string $body, ?int $now = null): bool
    {
        if ($timestamp === null || $signature === null || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $this->maxAgeSeconds) {
            return false;
        }

        return hash_equals($this->sign($timestamp, $body), $signature);
    }

    public function maxAgeSeconds(): int
    {
        return $this->maxAgeSeconds;
    }

    private function secret(): string
    {
        if ($this->secret === null || $this->secret === '') {
            throw new RuntimeException('INTERNAL_SHARED_SECRET is not set.');
        }

        return $this->secret;
    }
}
