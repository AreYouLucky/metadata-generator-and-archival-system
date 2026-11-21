<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_creates_a_verified_encoder_who_cannot_open_another_encoders_record(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'name' => 'Ana Encoder',
                'email' => 'ana@example.com',
                'role' => 'encoder',
                'password' => 'a-temporary-password',
                'daily_processing_minutes_limit' => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Created encoder account for Ana Encoder.');

        $encoder = User::where('email', 'ana@example.com')->sole();
        $this->assertSame(UserRole::Encoder, $encoder->role);
        $this->assertTrue($encoder->is_active);
        $this->assertNotNull($encoder->email_verified_at);
        $this->assertSame(60, $encoder->daily_processing_minutes_limit);
        $this->assertTrue(Hash::check('a-temporary-password', $encoder->password));

        $this->actingAs($encoder)
            ->get(route('records.show', Record::factory()->create()))
            ->assertForbidden();
    }

    public function test_viewer_accounts_cannot_be_created_here_and_emails_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'name' => 'Someone',
                'email' => $admin->email,
                'role' => 'user',
                'password' => 'a-temporary-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role']);
    }

    public function test_an_admin_changes_another_users_role_and_deactivates_them(): void
    {
        $encoder = User::factory()->encoder()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('admin.users.update', $encoder), ['role' => 'admin', 'is_active' => false])
            ->assertOk();

        $encoder->refresh();
        $this->assertSame(UserRole::Admin, $encoder->role);
        $this->assertFalse($encoder->is_active);
    }

    public function test_an_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.users.update', $admin), ['role' => 'encoder', 'is_active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role' => 'You cannot remove your own admin role.',
                'is_active' => 'You cannot deactivate your own account.',
            ]);

        $admin->refresh();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_the_user_list_is_searchable(): void
    {
        User::factory()->encoder()->create(['name' => 'Ana Encoder']);
        User::factory()->encoder()->create(['name' => 'Ben Encoder']);

        $this->actingAs(User::factory()->admin()->create(['name' => 'Zed Admin']))
            ->get(route('admin.users.index', ['q' => 'ana']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users-page/users')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Ana Encoder'));
    }

    public function test_encoders_cannot_manage_users(): void
    {
        $encoder = User::factory()->encoder()->create();

        $this->actingAs($encoder)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($encoder)->patchJson(route('admin.users.update', $encoder), ['role' => 'admin'])->assertForbidden();
    }
}
