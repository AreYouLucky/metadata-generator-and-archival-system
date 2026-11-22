<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\YouTubeCallbackRequest;
use App\Models\User;
use App\Models\YoutubePublication;
use App\Services\YouTube\PublishPanel;
use App\Services\YouTube\YouTubeException;
use App\Services\YouTube\YouTubeOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → YouTube: connect your own channel with Google OAuth, see what
 * you published, disconnect. Each user publishes only to the channel they
 * connected; tokens stay on the server (encrypted).
 */
class YouTubeConnectionController extends Controller
{
    private const string STATE_KEY = 'youtube_oauth_state';

    public function index(Request $request, YouTubeOAuth $oauth): Response
    {
        /** @var User $user */
        $user = $request->user();
        $account = $user->youtubeAccount()->first(['id', 'user_id', 'channel_id', 'channel_title', 'channel_thumbnail_url', 'created_at']);

        return Inertia::render('encoder/settings/youtube-page/youtube', [
            'configured' => $oauth->isConfigured(),
            'channel' => $account === null ? null : [
                'title' => $account->channel_title,
                'url' => $account->channelUrl(),
                'thumbnail_url' => $account->channel_thumbnail_url,
                'connected_at' => $account->created_at?->toIso8601String(),
            ],
            // Admins set up the Google OAuth client, so they see the redirect address to register.
            'redirectUri' => $user->isAdmin() ? $oauth->redirectUri() : null,
            'publications' => YoutubePublication::query()
                ->where('user_id', $user->id)
                ->with('record:id,title')
                ->latest('id')
                ->limit(PublishPanel::HISTORY_LIMIT)
                ->get(['id', 'record_id', 'user_id', 'status', 'privacy_status', 'title', 'channel_title', 'video_id', 'file_size', 'bytes_uploaded', 'error', 'thumbnail_error', 'youtube_upload_status', 'published_at', 'status_checked_at', 'created_at'])
                ->map(fn (YoutubePublication $publication): array => [
                    ...PublishPanel::row($publication),
                    'record' => $publication->record === null ? null : [
                        'id' => $publication->record->id,
                        'title' => $publication->record->title,
                    ],
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Off to Google's consent screen, with a one-time state against CSRF.
     */
    public function redirect(Request $request, YouTubeOAuth $oauth): RedirectResponse
    {
        if (! $oauth->isConfigured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'YouTube publishing is not set up on this server (YOUTUBE_CLIENT_ID and YOUTUBE_CLIENT_SECRET).']);

            return to_route('youtube.index');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($oauth->authorizationUrl($state));
    }

    public function callback(YouTubeCallbackRequest $request, YouTubeOAuth $oauth): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expected = $request->session()->pull(self::STATE_KEY);
        $state = (string) $request->validated('state');

        if (! is_string($expected) || ! hash_equals($expected, $state)) {
            return $this->back('error', 'The YouTube sign-in expired or did not start here. Try connecting again.');
        }

        if ($request->validated('error') !== null) {
            return $this->back('info', 'YouTube was not connected.');
        }

        try {
            $account = $oauth->connect($user, (string) $request->validated('code'));
        } catch (YouTubeException $exception) {
            Log::warning('YouTube connect failed', ['user_id' => $user->id, 'error' => $exception->getMessage()]);

            return $this->back('error', $exception->getMessage());
        }

        return $this->back('success', "Connected the YouTube channel {$account->channel_title}.");
    }

    /**
     * Revoke the grant and forget the channel. Publications keep their history.
     */
    public function destroy(Request $request, YouTubeOAuth $oauth): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $user->youtubeAccount()->first();

        if ($account !== null) {
            $oauth->disconnect($account);
        }

        return response()->json(['disconnected' => true]);
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('youtube.index');
    }
}
