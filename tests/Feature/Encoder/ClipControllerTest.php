<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\TaskStatus;
use App\Events\RecordClipUpdated;
use App\Jobs\StartClipJob;
use App\Models\Clip;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use App\Services\InternalSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Clips: cut a part of the master in the background, receive the checked
 * file from the AI service, download and delete it.
 */
class ClipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_clip_is_queued_and_listed_in_the_workspace(): void
    {
        Queue::fake([StartClipJob::class]);
        $record = $this->recordWithMaster();

        $this->actingAs($record->creator)
            ->postJson(route('records.clips.store', $record), ['start' => 12.5, 'end' => 40, 'title' => '  Mayor on the budget '])
            ->assertAccepted()
            ->assertJsonPath('clip.status', 'queued')
            ->assertJsonPath('clip.title', 'Mayor on the budget');

        $clip = Clip::query()->sole();
        $this->assertSame($record->creator->id, $clip->created_by);
        Queue::assertPushed(StartClipJob::class, fn (StartClipJob $job): bool => $job->clip->is($clip));
        $this->get(route('records.show', $record))->assertInertia(fn (Assert $page) => $page
            ->where('clips.0.id', $clip->id)
            ->where('clips.0.start', 12.5)
            ->where('clips.0.download_url', null));
    }

    public function test_the_clip_must_fit_the_video_and_the_length_limits(): void
    {
        config(['archive.clips.max_seconds' => 60]);
        $record = $this->recordWithMaster();
        $this->actingAs($record->creator);

        $this->postJson(route('records.clips.store', $record), ['start' => 10, 'end' => 5])
            ->assertJsonValidationErrors(['end' => 'The end field must be greater than 10.']);
        $this->postJson(route('records.clips.store', $record), ['start' => 10, 'end' => 10.5])
            ->assertJsonValidationErrors(['end' => 'A clip must be at least 1 second long.']);
        $this->postJson(route('records.clips.store', $record), ['start' => 0, 'end' => 90])
            ->assertJsonValidationErrors(['end' => 'A clip can be at most 1 minute long.']);
        $this->postJson(route('records.clips.store', $record), ['start' => 100, 'end' => 130])
            ->assertJsonValidationErrors(['end' => 'The clip ends after the video does.']);
        $this->assertSame(0, Clip::query()->count());
    }

    public function test_a_record_holds_a_limited_number_of_clips(): void
    {
        config(['archive.clips.max_per_record' => 1]);
        $record = $this->recordWithMaster();
        Clip::factory()->for($record)->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.clips.store', $record), ['start' => 0, 'end' => 10])
            ->assertJsonValidationErrors(['start' => 'This record already has the most clips it can hold. Delete some first.']);
    }

    public function test_another_encoder_cannot_cut_download_or_delete_clips(): void
    {
        $clip = Clip::factory()->done()->for($this->recordWithMaster())->create();
        $this->actingAs(User::factory()->encoder()->create());

        $this->postJson(route('records.clips.store', $clip->record), ['start' => 0, 'end' => 10])->assertForbidden();
        $this->get(route('records.clips.show', [$clip->record, $clip]))->assertForbidden();
        $this->deleteJson(route('records.clips.destroy', [$clip->record, $clip]))->assertForbidden();
    }

    public function test_a_clip_of_another_record_is_not_found(): void
    {
        $record = $this->recordWithMaster();
        $other = Clip::factory()->done()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('records.clips.show', [$record, $other]))
            ->assertNotFound();
    }

    public function test_the_ai_service_is_asked_to_cut_from_the_master_and_the_clip_starts(): void
    {
        Event::fake([RecordClipUpdated::class]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/clips' => Http::response(['job_id' => 'job-1'], 202)]);
        $clip = Clip::factory()->for($this->recordWithMaster())->create(['start' => 5, 'end' => 25]);

        StartClipJob::dispatchSync($clip);

        Http::assertSent(fn (Request $request): bool => $request['video_path'] === $clip->record->masterFile->storage_location
            && $request['clip_id'] === $clip->id
            && (float) $request['start'] === 5.0
            && (float) $request['end'] === 25.0
            && $request['output_path'] === "records/{$clip->record_id}/clips/{$clip->id}.mp4");
        $this->assertSame(TaskStatus::Processing, $clip->refresh()->status);
        Event::assertDispatched(RecordClipUpdated::class);
    }

    public function test_the_callback_stores_the_checked_file_and_a_download_is_logged(): void
    {
        Storage::fake('media');
        Event::fake([RecordClipUpdated::class]);
        $record = $this->recordWithMaster(['title' => 'Opening Ceremony']);
        $clip = Clip::factory()->processing()->for($record)->create(['title' => 'Mayor on the budget']);
        Storage::disk('media')->put($clip->storagePath(), 'mp4');

        $this->postSigned(route('internal.records.clips', [$record, $clip]), [
            'path' => $clip->storagePath(),
            'file_size' => 3,
            'md5' => str_repeat('c', 32),
            'sha256' => str_repeat('d', 64),
            'probe' => ['video_codec' => 'h264', 'duration' => 20.0, 'raw_probe' => ['big' => true]],
        ])->assertOk();

        $clip->refresh();
        $this->assertSame(TaskStatus::Done, $clip->status);
        $this->assertSame(str_repeat('d', 64), $clip->sha256_checksum);
        $this->assertSame(['video_codec' => 'h264', 'duration' => 20], $clip->technical);
        $this->actingAs($record->creator)
            ->get(route('records.clips.show', [$record, $clip]))
            ->assertOk()
            ->assertDownload('mayor-on-the-budget.mp4');
        $this->assertSame(AuditAction::Exported, $record->auditLogs()->sole()->action);
    }

    public function test_a_failure_is_stored_and_a_path_outside_the_clip_is_refused(): void
    {
        Event::fake([RecordClipUpdated::class]);
        $clip = Clip::factory()->processing()->for($this->recordWithMaster())->create();

        $this->postSigned(route('internal.records.clips', [$clip->record, $clip]), [
            'path' => "records/{$clip->record_id}/master/other.mp4",
            'file_size' => 3,
            'md5' => str_repeat('c', 32),
            'sha256' => str_repeat('d', 64),
        ])->assertUnprocessable();
        $this->postSigned(route('internal.records.clips', [$clip->record, $clip]), ['error' => 'ffmpeg could not cut the clip.'])->assertOk();

        $this->assertSame(TaskStatus::Failed, $clip->refresh()->status);
        $this->assertSame('ffmpeg could not cut the clip.', $clip->error);
    }

    public function test_a_finished_clip_is_deleted_with_its_file_but_not_while_it_is_cut(): void
    {
        Storage::fake('media');
        $record = $this->recordWithMaster();
        $busy = Clip::factory()->processing()->for($record)->create();
        $done = Clip::factory()->done()->for($record)->create();
        Storage::disk('media')->put($done->storagePath(), 'mp4');
        $this->actingAs($record->creator);

        $this->deleteJson(route('records.clips.destroy', [$record, $busy]))->assertConflict();
        $this->deleteJson(route('records.clips.destroy', [$record, $done]))->assertNoContent();

        $this->assertSame([$busy->id], Clip::query()->pluck('id')->all());
        Storage::disk('media')->assertMissing($done->storagePath());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordWithMaster(array $attributes = []): Record
    {
        $record = Record::factory()->create($attributes);
        RecordFile::factory()->for($record)->create()->technicalMetadata()->create(['duration' => 120]);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(string $uri, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars([
            ...app(InternalSignature::class)->headers($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }
}
