<?php

namespace Tests\Feature\Admin;

use App\Enums\PingSource;
use App\Events\SystemPingReceived;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_system_status_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.system.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/system-status-page/system-status')
                ->has('horizonUrl'));
    }

    public function test_encoders_and_viewers_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('admin.system.show'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.system.show'))
            ->assertForbidden();
    }

    public function test_a_deactivated_admin_is_signed_out(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get(route('admin.system.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.system.show'))->assertRedirect(route('login'));
    }

    public function test_the_shared_user_prop_exposes_only_the_fields_the_frontend_needs(): void
    {
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
            ->get(route('admin.system.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.user', fn (AssertableInertia $user) => $user
                    ->hasAll(['id', 'name', 'email', 'role', 'email_verified_at'])
                    ->where('role', 'admin')));
    }

    public function test_the_ai_health_endpoint_returns_the_service_status(): void
    {
        Http::fake(['ai-api.test/health' => Http::response(['status' => 'ok', 'version' => '0.1.0', 'gpu' => true, 'gpu_name' => 'NVIDIA GeForce RTX 3050'])]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.system.ai-health'))
            ->assertOk()
            ->assertJson(['reachable' => true, 'status' => 'ok', 'version' => '0.1.0', 'gpu' => true, 'gpu_name' => 'NVIDIA GeForce RTX 3050']);
    }

    public function test_a_broadcast_ping_dispatches_the_event_from_laravel(): void
    {
        Event::fake([SystemPingReceived::class]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.system.ping'), ['target' => 'broadcast'])
            ->assertAccepted();

        Event::assertDispatched(
            SystemPingReceived::class,
            fn (SystemPingReceived $event): bool => $event->pingId === $response->json('ping_id')
                && $event->source === PingSource::Laravel,
        );
    }

    public function test_an_ai_service_ping_is_forwarded_to_the_python_service(): void
    {
        Event::fake([SystemPingReceived::class]);
        Http::fake(['ai-api.test/ping' => Http::response(['job_id' => 'celery-123'], 202)]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.system.ping'), ['target' => 'ai_service'])
            ->assertAccepted()
            ->assertJson(['job_id' => 'celery-123']);

        Http::assertSent(fn (Request $request): bool => $request['ping_id'] === $response->json('ping_id'));
        Event::assertNotDispatched(SystemPingReceived::class);
    }

    public function test_an_unreachable_ai_service_returns_bad_gateway(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.system.ping'), ['target' => 'ai_service'])
            ->assertStatus(502);
    }

    public function test_the_ping_target_must_be_a_known_value(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.system.ping'), ['target' => 'everything'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target');
    }

    public function test_encoders_cannot_send_test_pings(): void
    {
        Event::fake([SystemPingReceived::class]);

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('admin.system.ping'), ['target' => 'broadcast'])
            ->assertForbidden();

        Event::assertNotDispatched(SystemPingReceived::class);
    }
}
