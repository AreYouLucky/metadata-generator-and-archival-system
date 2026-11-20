<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Development-only password for the seeded accounts.
     */
    private const string DEV_PASSWORD = 'password';

    /**
     * Create the first admin from config/archive.php (ADMIN_NAME, ADMIN_EMAIL,
     * ADMIN_PASSWORD). On local and testing it also creates one encoder and one
     * public viewer, and falls back to the password "password".
     *
     * Safe to re-run: existing accounts keep their password; the admin account
     * is only re-promoted, re-activated and marked verified.
     */
    public function run(): void
    {
        $isDevelopment = app()->environment('local', 'testing');

        $adminPassword = config('archive.admin.password');

        if (! is_string($adminPassword) || $adminPassword === '') {
            $adminPassword = $isDevelopment ? self::DEV_PASSWORD : null;
        }

        $this->ensureUser(
            name: (string) config('archive.admin.name'),
            email: (string) config('archive.admin.email'),
            role: UserRole::Admin,
            password: $adminPassword,
        );

        if (! $isDevelopment) {
            return;
        }

        $this->ensureUser('Encoder', 'encoder@example.com', UserRole::Encoder, self::DEV_PASSWORD);
        $this->ensureUser('Viewer', 'user@example.com', UserRole::User, self::DEV_PASSWORD);
    }

    private function ensureUser(string $name, string $email, UserRole $role, ?string $password): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            if ($password === null) {
                throw new RuntimeException('Set ADMIN_PASSWORD in .env before seeding outside local/testing.');
            }

            $user->name = $name;
            $user->password = $password;
        }

        $user->forceFill([
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
