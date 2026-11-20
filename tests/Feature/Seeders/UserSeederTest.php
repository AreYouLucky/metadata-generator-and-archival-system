<?php

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_active_admin_from_config(): void
    {
        config(['archive.admin' => ['name' => 'Records Officer', 'email' => 'records@agency.test', 'password' => 'a-long-secret']]);

        $this->seed(UserSeeder::class);

        $admin = User::query()->where('email', 'records@agency.test')->firstOrFail();
        $this->assertSame('Records Officer', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('a-long-secret', $admin->password));
    }

    public function test_in_development_it_adds_one_encoder_and_one_viewer_with_the_dev_password(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertSame(UserRole::Encoder, User::query()->where('email', 'encoder@example.com')->value('role'));
        $this->assertSame(UserRole::User, User::query()->where('email', 'user@example.com')->value('role'));
        $this->assertTrue(Hash::check('password', (string) User::query()->where('email', 'admin@example.com')->value('password')));
    }

    public function test_re_running_keeps_changed_passwords_and_restores_the_admin_role(): void
    {
        $this->seed(UserSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $admin->forceFill(['password' => 'changed-later', 'role' => UserRole::User, 'is_active' => false])->save();

        $this->seed(UserSeeder::class);

        $admin->refresh();
        $this->assertSame(3, User::query()->count());
        $this->assertTrue(Hash::check('changed-later', $admin->password));
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_outside_development_it_refuses_to_create_an_admin_without_a_password(): void
    {
        $this->app['env'] = 'production';

        try {
            app(UserSeeder::class)->run();
            $this->fail('The seeder should refuse to run without ADMIN_PASSWORD.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ADMIN_PASSWORD', $exception->getMessage());
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_outside_development_it_creates_only_the_admin(): void
    {
        $this->app['env'] = 'production';
        config(['archive.admin.password' => 'a-long-secret']);

        app(UserSeeder::class)->run();

        $this->assertSame(['admin@example.com'], User::query()->pluck('email')->all());
    }
}
