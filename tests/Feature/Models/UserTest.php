<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, UserRole, bool}>
     */
    public static function roleChecks(): array
    {
        return [
            'admin passes the encoder check' => [UserRole::Admin, UserRole::Encoder, true],
            'admin passes the user check' => [UserRole::Admin, UserRole::User, true],
            'encoder passes the encoder check' => [UserRole::Encoder, UserRole::Encoder, true],
            'encoder fails the admin check' => [UserRole::Encoder, UserRole::Admin, false],
            'user fails the encoder check' => [UserRole::User, UserRole::Encoder, false],
        ];
    }

    #[DataProvider('roleChecks')]
    public function test_role_check_result_depends_on_role_and_admins_pass_every_check(UserRole $role, UserRole $required, bool $expected): void
    {
        $user = User::factory()->make(['role' => $role]);

        $this->assertSame($expected, $user->hasRole($required));
    }

    public function test_new_users_default_to_the_public_user_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_deleting_an_encoder_who_created_records_is_rejected(): void
    {
        $encoder = User::factory()->encoder()->create();
        Record::factory()->for($encoder, 'creator')->create();

        $this->expectException(QueryException::class);

        $encoder->delete();
    }
}
