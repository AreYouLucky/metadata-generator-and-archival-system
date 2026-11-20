<?php

namespace Tests\Feature\Models;

use App\Enums\AccessLevel;
use App\Enums\PermissionStatus;
use App\Models\RecordRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordRightsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{AccessLevel, PermissionStatus, bool}>
     */
    public static function rights(): array
    {
        return [
            'public and approved' => [AccessLevel::Public, PermissionStatus::Approved, true],
            'public but pending' => [AccessLevel::Public, PermissionStatus::Pending, false],
            'public but denied' => [AccessLevel::Public, PermissionStatus::Denied, false],
            'internal and approved' => [AccessLevel::Internal, PermissionStatus::Approved, false],
            'restricted and approved' => [AccessLevel::Restricted, PermissionStatus::Approved, false],
        ];
    }

    #[DataProvider('rights')]
    public function test_public_access_requires_a_public_level_and_approved_permission(AccessLevel $level, PermissionStatus $permission, bool $expected): void
    {
        $rights = RecordRights::factory()->make([
            'access_level' => $level,
            'permission_status' => $permission,
        ]);

        $this->assertSame($expected, $rights->allowsPublicAccess());
    }

    public function test_an_embargo_in_force_blocks_public_access(): void
    {
        $rights = RecordRights::factory()->publicAccess()->make(['embargo_until' => now()->addMinute()]);

        $this->assertTrue($rights->isEmbargoed());
        $this->assertFalse($rights->allowsPublicAccess());
    }

    public function test_public_access_resumes_once_the_embargo_has_passed(): void
    {
        $rights = RecordRights::factory()->publicAccess()->make(['embargo_until' => now()->subMinute()]);

        $this->assertFalse($rights->isEmbargoed());
        $this->assertTrue($rights->allowsPublicAccess());
    }
}
