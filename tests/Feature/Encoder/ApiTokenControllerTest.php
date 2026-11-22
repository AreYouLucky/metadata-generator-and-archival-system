<?php

namespace Tests\Feature\Encoder;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Settings → API tokens: create (shown once), revoke your own, and the
 * webhook signing secret.
 */
class ApiTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_lists_the_encoders_own_tokens(): void
    {
        $encoder = User::factory()->encoder()->create();
        $encoder->createToken('Newsroom', ['records:read']);
        User::factory()->encoder()->create()->createToken('Someone else', ['records:read']);

        $this->actingAs($encoder)
            ->get(route('api-tokens.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/settings/api-tokens-page/api-tokens')
                ->has('tokens', 1)
                ->where('tokens.0.name', 'Newsroom')
                ->where('hasWebhookSecret', false));
    }

    public function test_a_viewer_account_has_no_api_tokens_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('api-tokens.index'))->assertForbidden();
    }

    public function test_a_new_token_is_shown_once_and_works_with_its_abilities(): void
    {
        $this->freezeTime();
        $encoder = User::factory()->encoder()->create();

        $response = $this->actingAs($encoder)->postJson(route('api-tokens.store'), [
            'name' => 'Newsroom uploader',
            'abilities' => ['records:read'],
            'expires_in_days' => 30,
        ])->assertCreated()->assertJsonPath('token.abilities', ['records:read']);

        $token = PersonalAccessToken::query()->sole();
        $this->assertTrue($token->tokenable->is($encoder));
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $token->expires_at?->toDateTimeString());
        $this->assertNotSame($response->json('plain_text'), $token->token);

        $this->app['auth']->forgetGuards();
        $this->withToken((string) $response->json('plain_text'))->getJson(route('api.v1.records.index'))->assertOk();
        $this->withToken((string) $response->json('plain_text'))->postJson(route('api.v1.records.store'))->assertForbidden();
    }

    public function test_a_token_needs_a_known_ability_and_expiry(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('api-tokens.store'), ['name' => 'x', 'abilities' => ['records:delete'], 'expires_in_days' => 7])
            ->assertJsonValidationErrors(['abilities.0', 'expires_in_days']);
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_an_encoder_revokes_only_their_own_tokens(): void
    {
        $encoder = User::factory()->encoder()->create();
        $own = $encoder->createToken('Mine', ['records:read'])->accessToken;
        $foreign = User::factory()->encoder()->create()->createToken('Theirs', ['records:read'])->accessToken;
        $this->actingAs($encoder);

        $this->deleteJson(route('api-tokens.destroy', $foreign->id))->assertForbidden();
        $this->deleteJson(route('api-tokens.destroy', $own->id))->assertOk();

        $this->assertModelMissing($own);
        $this->assertModelExists($foreign);
    }

    public function test_the_webhook_secret_is_shown_once_and_stored_encrypted(): void
    {
        $encoder = User::factory()->encoder()->create();

        $secret = $this->actingAs($encoder)
            ->postJson(route('api-tokens.webhook-secret'))
            ->assertCreated()
            ->json('secret');

        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertSame($secret, $encoder->refresh()->webhook_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $encoder->id)->value('webhook_secret'));
    }
}
