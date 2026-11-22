<?php

namespace Tests\Feature\Encoder;

use App\Models\User;
use App\Models\YoutubeAccount;
use App\Models\YoutubePublication;
use App\Services\YouTube\YouTubeOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings → YouTube: Google OAuth connect (state checked, both scopes
 * required, tokens encrypted), and disconnect (grant revoked, history kept).
 */
class YouTubeConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const string SCOPES = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.youtube.client_id' => 'client-123', 'services.youtube.client_secret' => 'secret-456']);
    }

    public function test_connecting_sends_the_encoder_to_googles_consent_screen_with_a_state(): void
    {
        $response = $this->actingAs(User::factory()->encoder()->create())->get(route('youtube.connect'));

        $state = session('youtube_oauth_state');
        $this->assertIsString($state);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(YouTubeOAuth::AUTH_URL.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame($state, $query['state']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame(self::SCOPES, $query['scope']);
        $this->assertSame(route('youtube.callback'), $query['redirect_uri']);
    }

    public function test_connecting_is_refused_when_the_server_has_no_google_client(): void
    {
        config(['services.youtube.client_id' => null]);

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('youtube.connect'))
            ->assertRedirect(route('youtube.index'));
    }

    public function test_a_callback_with_the_wrong_state_connects_nothing(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $encoder = User::factory()->encoder()->create();

        $this->actingAs($encoder)
            ->withSession(['youtube_oauth_state' => str_repeat('a', 40)])
            ->get(route('youtube.callback', ['state' => str_repeat('b', 40), 'code' => 'abc']))
            ->assertRedirect(route('youtube.index'));

        Http::assertNothingSent();
        $this->assertNull($encoder->youtubeAccount);
    }

    public function test_a_successful_callback_saves_the_channel_with_encrypted_tokens(): void
    {
        $this->fakeGoogle(self::SCOPES);
        $encoder = User::factory()->encoder()->create();
        $state = str_repeat('s', 40);

        $this->actingAs($encoder)
            ->withSession(['youtube_oauth_state' => $state])
            ->get(route('youtube.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertRedirect(route('youtube.index'));

        $account = $encoder->youtubeAccount()->sole();
        $this->assertSame('UCchannel', $account->channel_id);
        $this->assertSame('DOST Region 10', $account->channel_title);
        $this->assertSame('ya29.access', $account->access_token);
        $this->assertSame('1//refresh', $account->refresh_token);
        $this->assertNotSame('1//refresh', DB::table('youtube_accounts')->value('refresh_token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === YouTubeOAuth::TOKEN_URL
            && $request['code'] === 'auth-code'
            && $request['client_secret'] === 'secret-456');
    }

    public function test_a_grant_without_the_upload_permission_is_refused(): void
    {
        $this->fakeGoogle('https://www.googleapis.com/auth/youtube.readonly');
        $encoder = User::factory()->encoder()->create();
        $state = str_repeat('s', 40);

        $this->actingAs($encoder)
            ->withSession(['youtube_oauth_state' => $state])
            ->get(route('youtube.callback', ['state' => $state, 'code' => 'auth-code']));

        $this->assertNull($encoder->youtubeAccount);
    }

    public function test_disconnecting_revokes_the_grant_and_keeps_the_publication_history(): void
    {
        Http::preventStrayRequests();
        Http::fake([YouTubeOAuth::REVOKE_URL => Http::response()]);
        $account = YoutubeAccount::factory()->create();
        $publication = YoutubePublication::factory()->for($account, 'account')->published()->create();

        $this->actingAs($account->user)->deleteJson(route('youtube.destroy'))->assertOk();

        $this->assertModelMissing($account);
        $this->assertNull($publication->refresh()->youtube_account_id);
        Http::assertSent(fn (Request $request): bool => $request->url() === YouTubeOAuth::REVOKE_URL && $request['token'] === $account->refresh_token);
    }

    public function test_the_settings_page_shows_the_channel_but_never_its_tokens(): void
    {
        $account = YoutubeAccount::factory()->create(['channel_title' => 'Archive channel']);

        $this->actingAs($account->user)
            ->get(route('youtube.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('encoder/settings/youtube-page/youtube')
                ->where('channel.title', 'Archive channel')
                ->missing('channel.access_token')
                ->where('redirectUri', null));
    }

    private function fakeGoogle(string $scopes): void
    {
        Http::preventStrayRequests();
        Http::fake([
            YouTubeOAuth::TOKEN_URL => Http::response([
                'access_token' => 'ya29.access',
                'refresh_token' => '1//refresh',
                'expires_in' => 3599,
                'scope' => $scopes,
            ]),
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['id' => 'UCchannel', 'snippet' => ['title' => 'DOST Region 10']]],
            ]),
        ]);
    }
}
