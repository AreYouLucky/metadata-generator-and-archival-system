<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_public_viewers_are_sent_to_the_catalogue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('catalogue.home'));
    }

    public function test_encoders_and_admins_are_sent_to_their_record_history(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('records.index'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('records.index'));
    }
}
