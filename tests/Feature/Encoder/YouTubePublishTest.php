<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Enums\ReviewStatus;
use App\Exporters\JsonExporter;
use App\Jobs\CheckYouTubeStatusJob;
use App\Jobs\PublishToYouTubeJob;
use App\Jobs\SetYouTubeThumbnailJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use App\Models\YoutubeAccount;
use App\Models\YoutubePublication;
use App\Services\YouTube\YouTubeOAuth;
use App\Services\YouTube\YouTubePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Publishing to YouTube: only records that may leave the archive, to the
 * encoder's own channel, as a resumable upload in chunks that resumes where
 * YouTube got to, then followed until YouTube has processed it.
 */
class YouTubePublishTest extends TestCase
{
    use RefreshDatabase;

    private const string START_URL = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=*';

    private const string SESSION = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&upload_id=session-1';

    private const string STATUS_URL = 'https://www.googleapis.com/youtube/v3/videos*';

    private const int MEGABYTE = 1024 * 1024;

    public function test_a_record_that_is_not_approved_cannot_be_published(): void
    {
        $account = YoutubeAccount::factory()->create();
        $record = $this->recordWithMaster($account->user, ['review_status' => ReviewStatus::Draft]);

        $this->actingAs($account->user)->postJson(route('records.youtube.store', $record), $this->choices())->assertForbidden();
        $this->assertSame(0, YoutubePublication::query()->count());
    }

    public function test_another_encoders_approved_record_cannot_be_published(): void
    {
        $account = YoutubeAccount::factory()->create();
        $record = $this->recordWithMaster(User::factory()->encoder()->create());

        $this->actingAs($account->user)->postJson(route('records.youtube.store', $record), $this->choices())->assertForbidden();
    }

    public function test_publishing_needs_a_connected_channel(): void
    {
        $encoder = User::factory()->encoder()->create();
        $record = $this->recordWithMaster($encoder);

        $this->actingAs($encoder)
            ->postJson(route('records.youtube.store', $record), $this->choices())
            ->assertJsonValidationErrors(['channel' => 'Connect a YouTube channel in Settings → YouTube first.']);
    }

    public function test_youtubes_limits_on_title_and_tags_are_checked(): void
    {
        $account = YoutubeAccount::factory()->create();
        $record = $this->recordWithMaster($account->user);
        $this->actingAs($account->user);

        $this->postJson(route('records.youtube.store', $record), $this->choices(['title' => 'Budget <b>hearing</b>']))
            ->assertJsonValidationErrors(['title' => 'YouTube does not allow < or > in the title.']);
        $this->postJson(route('records.youtube.store', $record), $this->choices(['tags' => array_map(fn (int $i): string => str_repeat('t', 90).$i, range(1, 6))]))
            ->assertJsonValidationErrors(['tags' => 'YouTube allows at most 500 characters of tags in total.']);
    }

    public function test_an_approved_record_is_uploaded_in_chunks_and_followed_until_published(): void
    {
        config(['archive.youtube.chunk_mb' => 1]);
        Storage::fake('media');
        Queue::fake([PublishToYouTubeJob::class, SetYouTubeThumbnailJob::class, CheckYouTubeStatusJob::class]);
        $account = YoutubeAccount::factory()->expired()->create();
        $record = $this->recordWithMaster($account->user, [], (int) (2.5 * self::MEGABYTE));
        $this->fakeYouTube(Http::sequence()
            ->push('', 308, ['Range' => 'bytes=0-1048575'])
            ->push('', 308, ['Range' => 'bytes=0-2097151'])
            ->push(['id' => 'vid_1234567', 'status' => ['uploadStatus' => 'uploaded']], 201));

        $this->actingAs($account->user)
            ->postJson(route('records.youtube.store', $record), $this->choices(['privacy_status' => 'private', 'tags' => ['dost', 'science fair']]))
            ->assertAccepted()
            ->assertJsonPath('publication.status', 'queued');
        $this->runPublishing();

        $publication = YoutubePublication::query()->sole();
        $this->assertSame(PublicationStatus::Published, $publication->status);
        $this->assertSame('vid_1234567', $publication->video_id);
        $this->assertSame(['dost', 'science fair'], $publication->tags);
        $this->assertSame(
            ['bytes 0-1048575/2621440', 'bytes 1048576-2097151/2621440', 'bytes 2097152-2621439/2621440'],
            Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')->map(fn (array $pair): string => $pair[0]->header('Content-Range')[0])->values()->all(),
        );
        // The expired sign-in was refreshed before the upload started.
        Http::assertSent(fn (Request $request): bool => $request->url() === YouTubeOAuth::TOKEN_URL && $request['grant_type'] === 'refresh_token');
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Published->value,
            'performed_by' => $account->user_id,
        ]);
        $document = app(JsonExporter::class)->document($record);
        $this->assertSame('vid_1234567', $document['publishing']['youtube']['videoId']);
    }

    public function test_an_interrupted_upload_resumes_where_youtube_got_to(): void
    {
        config(['archive.youtube.chunk_mb' => 4]);
        Storage::fake('media');
        Queue::fake([PublishToYouTubeJob::class, SetYouTubeThumbnailJob::class, CheckYouTubeStatusJob::class]);
        $account = YoutubeAccount::factory()->create();
        $record = $this->recordWithMaster($account->user, [], 3 * self::MEGABYTE);
        $publication = YoutubePublication::factory()->for($record)->for($account, 'account')->uploading(3 * self::MEGABYTE, 0)->create(['upload_session_url' => self::SESSION]);
        $this->fakeYouTube(Http::sequence()
            ->push('', 308, ['Range' => 'bytes=0-1048575'])
            ->push(['id' => 'vid_resumed', 'status' => ['uploadStatus' => 'uploaded']], 201));

        (new PublishToYouTubeJob($publication))->handle(app(YouTubePublisher::class));

        $this->assertSame(
            ['bytes */3145728', 'bytes 1048576-3145727/3145728'],
            Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')->map(fn (array $pair): string => $pair[0]->header('Content-Range')[0])->values()->all(),
        );
        $this->assertSame('vid_resumed', $publication->refresh()->video_id);
        $this->assertSame(PublicationStatus::Processing, $publication->status);
    }

    public function test_an_upload_stops_when_the_record_may_no_longer_leave_the_archive(): void
    {
        Storage::fake('media');
        Http::preventStrayRequests();
        Http::fake();
        $account = YoutubeAccount::factory()->create();
        $record = $this->recordWithMaster($account->user);
        $publication = YoutubePublication::factory()->for($record)->for($account, 'account')->create();
        // Reopened by an admin while the upload waited in the queue.
        $record->forceFill(['review_status' => ReviewStatus::Draft, 'published_at' => null])->save();

        (new PublishToYouTubeJob($publication))->handle(app(YouTubePublisher::class));

        $this->assertSame(PublicationStatus::Failed, $publication->refresh()->status);
        $this->assertSame('This record may no longer leave the archive: It is not approved.', $publication->error);
        Http::assertNothingSent();
    }

    public function test_a_quota_error_fails_the_publication_with_youtubes_reason(): void
    {
        Storage::fake('media');
        Http::preventStrayRequests();
        Http::fake([self::START_URL => Http::response(['error' => ['code' => 403, 'message' => 'quota', 'errors' => [['reason' => 'quotaExceeded']]]], 403)]);
        $account = YoutubeAccount::factory()->create();
        $publication = YoutubePublication::factory()->for($this->recordWithMaster($account->user))->for($account, 'account')->create();

        (new PublishToYouTubeJob($publication))->handle(app(YouTubePublisher::class));

        $this->assertSame(PublicationStatus::Failed, $publication->refresh()->status);
        $this->assertSame('The YouTube API quota for today is used up. Try again tomorrow.', $publication->error);
    }

    public function test_a_video_youtube_rejects_is_marked_failed_with_the_reason(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::STATUS_URL => Http::response(['items' => [['status' => ['uploadStatus' => 'rejected', 'rejectionReason' => 'duplicate']]]])]);
        $publication = YoutubePublication::factory()->processing()->create();

        (new CheckYouTubeStatusJob($publication))->handle(app(YouTubePublisher::class));

        $this->assertSame(PublicationStatus::Failed, $publication->refresh()->status);
        $this->assertSame('YouTube rejected the video (duplicate).', $publication->error);
    }

    public function test_a_refused_custom_thumbnail_is_noted_and_the_video_stays_published(): void
    {
        Storage::fake('media');
        Http::preventStrayRequests();
        Http::fake(['https://www.googleapis.com/upload/youtube/v3/thumbnails/set*' => Http::response(['error' => ['code' => 403, 'message' => 'forbidden', 'errors' => [['reason' => 'forbidden']]]], 403)]);
        $account = YoutubeAccount::factory()->create();
        $record = $this->recordWithMaster($account->user);
        Storage::disk('media')->put("records/{$record->id}/thumbnail.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $record->forceFill(['thumbnail_path' => "records/{$record->id}/thumbnail.png"])->save();
        $publication = YoutubePublication::factory()->for($record)->for($account, 'account')->published()->create(['with_thumbnail' => true]);

        (new SetYouTubeThumbnailJob($publication))->handle(app(YouTubePublisher::class));

        $this->assertSame('YouTube allows custom thumbnails only on verified channels, so it chose one.', $publication->refresh()->thumbnail_error);
        $this->assertSame(PublicationStatus::Published, $publication->status);
    }

    public function test_the_workspace_says_why_a_record_cannot_be_published_yet(): void
    {
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->where('publishing.blockers', ['It is not approved.'])
                ->where('publishing.can_publish', false)
                ->where('publishing.channel', null)
                ->where('publications', []));
    }

    /**
     * Runs the queued publish steps (each queues the next), then the
     * thumbnail and status jobs they queued.
     */
    private function runPublishing(): void
    {
        $publisher = app(YouTubePublisher::class);

        for ($handled = 0; ($jobs = Queue::pushed(PublishToYouTubeJob::class)->values())->count() > $handled; $handled++) {
            $jobs[$handled]->handle($publisher);
        }

        Queue::pushed(SetYouTubeThumbnailJob::class)->each(fn (SetYouTubeThumbnailJob $job) => $job->handle($publisher));
        Queue::pushed(CheckYouTubeStatusJob::class)->each(fn (CheckYouTubeStatusJob $job) => $job->handle($publisher));
    }

    private function fakeYouTube(ResponseSequence $uploadAnswers): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::SESSION => $uploadAnswers,
            self::START_URL => Http::response('', 200, ['Location' => self::SESSION]),
            self::STATUS_URL => Http::response(['items' => [['status' => ['uploadStatus' => 'processed', 'privacyStatus' => 'private'], 'processingDetails' => ['processingStatus' => 'succeeded']]]]),
            YouTubeOAuth::TOKEN_URL => Http::response(['access_token' => 'ya29.fresh', 'expires_in' => 3599]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function choices(array $overrides = []): array
    {
        return [
            'privacy_status' => 'private',
            'title' => 'Regional science fair opening',
            'description' => "Highlights.\n\n00:00 Opening",
            'tags' => ['dost'],
            'category_id' => '28',
            'made_for_kids' => false,
            'with_thumbnail' => false,
            ...$overrides,
        ];
    }

    /**
     * An approved record of `$owner` with a master file of `$bytes` bytes
     * on the (faked, when the test fakes it) media disk.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordWithMaster(User $owner, array $attributes = [], int $bytes = 2048): Record
    {
        $record = Record::factory()->approved()->for($owner, 'creator')->create($attributes);
        $location = "records/{$record->id}/master/video.mp4";
        RecordFile::factory()->for($record)->create(['storage_location' => $location, 'file_size' => $bytes]);
        Storage::disk('media')->put($location, str_repeat('v', $bytes));

        return $record;
    }
}
