<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_horizon(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/horizon')
            ->assertOk();
    }

    public function test_non_admins_are_refused_even_in_the_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->encoder()->create())
            ->get('/horizon')
            ->assertForbidden();
    }

    public function test_deactivated_admins_are_signed_out(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get('/horizon')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/horizon')->assertRedirect(route('login'));
    }
}
