<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivatedAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $user = User::factory()->encoder()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact an administrator.']);

        $this->assertGuest();
    }

    public function test_a_wrong_password_does_not_reveal_that_the_account_is_deactivated(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_a_session_of_an_account_deactivated_after_sign_in_is_ended(): void
    {
        $user = User::factory()->encoder()->inactive()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
