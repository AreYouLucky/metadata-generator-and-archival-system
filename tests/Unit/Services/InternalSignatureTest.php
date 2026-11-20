<?php

namespace Tests\Unit\Services;

use App\Services\InternalSignature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InternalSignatureTest extends TestCase
{
    private const string BODY = '{"ping_id":"0b5a5a3e-7c1e-4d3a-9f0e-2a6f4b8c9d10","source":"ai-worker"}';

    private const int NOW = 1700000000;

    /**
     * Shared with ai-service/tests/test_security.py so both sides provably use the same scheme.
     */
    private const string KNOWN_SIGNATURE = 'sha256=a998dbaa4c5c8ea69f36179186ceefac68ae5f80c86e5b823cd177ee8871f1cb';

    private InternalSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signature = new InternalSignature('test-secret', 300);
    }

    public function test_it_matches_the_signature_the_python_service_produces(): void
    {
        $this->assertSame(self::KNOWN_SIGNATURE, $this->signature->sign((string) self::NOW, self::BODY));
        $this->assertSame(
            ['X-Timestamp' => (string) self::NOW, 'X-Signature' => self::KNOWN_SIGNATURE],
            $this->signature->headers(self::BODY, self::NOW),
        );
    }

    public function test_it_accepts_a_fresh_signature_of_the_exact_body(): void
    {
        $this->assertTrue($this->signature->verify((string) self::NOW, self::KNOWN_SIGNATURE, self::BODY, self::NOW + 299));
    }

    /**
     * @return array<string, array{string|null, string|null, string, int}>
     */
    public static function invalidRequests(): array
    {
        $timestamp = (string) self::NOW;

        return [
            'tampered body' => [$timestamp, self::KNOWN_SIGNATURE, str_replace('ai-worker', 'laravel', self::BODY), self::NOW],
            'older than five minutes' => [$timestamp, self::KNOWN_SIGNATURE, self::BODY, self::NOW + 301],
            'more than five minutes in the future' => [$timestamp, self::KNOWN_SIGNATURE, self::BODY, self::NOW - 301],
            'missing timestamp' => [null, self::KNOWN_SIGNATURE, self::BODY, self::NOW],
            'missing signature' => [$timestamp, null, self::BODY, self::NOW],
            'non-numeric timestamp' => ['1700000000.0', self::KNOWN_SIGNATURE, self::BODY, self::NOW],
            'signature without the algorithm prefix' => [$timestamp, substr(self::KNOWN_SIGNATURE, 7), self::BODY, self::NOW],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_it_rejects_invalid_requests(?string $timestamp, ?string $signature, string $body, int $now): void
    {
        $this->assertFalse($this->signature->verify($timestamp, $signature, $body, $now));
    }

    public function test_a_different_secret_does_not_verify(): void
    {
        $other = new InternalSignature('another-secret', 300);

        $this->assertFalse($other->verify((string) self::NOW, self::KNOWN_SIGNATURE, self::BODY, self::NOW));
    }

    public function test_it_refuses_to_sign_or_verify_without_a_secret(): void
    {
        $this->expectException(RuntimeException::class);

        (new InternalSignature('', 300))->verify((string) self::NOW, self::KNOWN_SIGNATURE, self::BODY, self::NOW);
    }
}
