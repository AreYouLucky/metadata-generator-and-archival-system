<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordFrame;
use App\Models\User;
use Database\Factories\RecordFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThumbnailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_key_frame_becomes_the_thumbnail_as_a_copy_and_is_logged(): void
    {
        Storage::fake('media');
        [$record, $frame] = $this->recordWithFrame();
        $old = "records/{$record->id}/thumbnail/old.jpg";
        Storage::disk('media')->put($old, 'old');
        $record->thumbnail_path = $old;
        $record->save();

        $response = $this->actingAs($record->creator)
            ->postJson(route('records.thumbnail.store', $record), ['frame_id' => $frame->id])
            ->assertOk()
            ->assertJsonPath('thumbnail.frame_id', $frame->id)
            ->assertJsonPath('thumbnail.time_sec', 12.5);

        $record->refresh();
        $this->assertSame('frame image', Storage::disk('media')->get((string) $record->thumbnail_path));
        Storage::disk('media')->assertMissing($old);
        $this->assertStringContainsString(route('records.thumbnail', $record), (string) $response->json('thumbnail.url'));
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Updated->value,
            'remarks' => 'Thumbnail: key frame at 0:12.',
        ]);
    }

    public function test_a_frame_of_another_record_is_refused(): void
    {
        Storage::fake('media');
        [$record] = $this->recordWithFrame();
        $foreign = RecordFrame::factory()->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.thumbnail.store', $record), ['frame_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['frame_id' => 'That frame does not belong to this record.']);

        $this->assertNull($record->refresh()->thumbnail_path);
    }

    public function test_the_current_frame_is_captured_from_the_master_by_the_ai_service(): void
    {
        Storage::fake('media');
        [$record] = $this->recordWithFrame();
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/frames/capture' => function (Request $request) {
            Storage::disk('media')->put($request['output_path'], 'captured');

            return Http::response(['path' => $request['output_path']]);
        }]);

        $this->actingAs($record->creator)
            ->postJson(route('records.thumbnail.store', $record), ['time_sec' => 42.25])
            ->assertOk()
            ->assertJsonPath('thumbnail.frame_id', null)
            ->assertJsonPath('thumbnail.time_sec', 42.25);

        Http::assertSent(fn (Request $request): bool => $request['video_path'] === $record->masterFile->storage_location
            && $request['time_sec'] === 42.25
            && str_starts_with($request['output_path'], "records/{$record->id}/thumbnail/"));
        $this->assertSame('captured', Storage::disk('media')->get((string) $record->refresh()->thumbnail_path));
    }

    public function test_a_time_past_the_end_of_the_video_is_refused(): void
    {
        [$record] = $this->recordWithFrame();
        Http::preventStrayRequests();

        $this->actingAs($record->creator)
            ->postJson(route('records.thumbnail.store', $record), ['time_sec' => 61])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['time_sec' => 'That time is past the end of the video.']);
    }

    public function test_an_uploaded_image_becomes_the_thumbnail(): void
    {
        Storage::fake('media');
        [$record] = $this->recordWithFrame();

        $this->actingAs($record->creator)
            ->post(route('records.thumbnail.store', $record), ['image' => $this->png('cover.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('thumbnail.time_sec', null);

        $path = (string) $record->refresh()->thumbnail_path;
        $this->assertMatchesRegularExpression("#^records/{$record->id}/thumbnail/[a-z0-9]{20}\\.png$#", $path);
        Storage::disk('media')->assertExists($path);
    }

    public function test_choosing_two_ways_at_once_is_refused(): void
    {
        [$record, $frame] = $this->recordWithFrame();

        $this->actingAs($record->creator)
            ->postJson(route('records.thumbnail.store', $record), ['frame_id' => $frame->id, 'time_sec' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['frame_id', 'time_sec']);
    }

    public function test_another_encoder_can_neither_set_nor_see_the_thumbnail_or_frames(): void
    {
        Storage::fake('media');
        [$record, $frame] = $this->recordWithFrame();
        $record->thumbnail_path = "records/{$record->id}/thumbnail/a.jpg";
        $record->save();
        Storage::disk('media')->put($record->thumbnail_path, 'image');
        $stranger = User::factory()->encoder()->create();

        $this->actingAs($stranger)->postJson(route('records.thumbnail.store', $record), ['frame_id' => $frame->id])->assertForbidden();
        $this->actingAs($stranger)->get(route('records.thumbnail', $record))->assertForbidden();
        $this->actingAs($stranger)->get(route('records.frames.image', [$record, $frame]))->assertForbidden();
    }

    public function test_an_approved_record_is_read_only_for_its_encoder(): void
    {
        [$record, $frame] = $this->recordWithFrame(Record::factory()->approved());

        $this->actingAs($record->creator)
            ->postJson(route('records.thumbnail.store', $record), ['frame_id' => $frame->id])
            ->assertForbidden();
    }

    public function test_the_owner_sees_the_frames_and_the_thumbnail_in_the_workspace(): void
    {
        Storage::fake('media');
        [$record, $frame] = $this->recordWithFrame();
        $other = Record::factory()->create(['created_by' => $record->created_by]);

        $this->actingAs($record->creator)
            ->get(route('records.frames.image', [$record, $frame]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        // A frame is only reachable through its own record.
        $this->get(route('records.frames.image', [$other, $frame]))->assertNotFound();
        $this->get(route('records.show', $record))->assertInertia(fn (Assert $page) => $page
            ->where('visuals.frames.0.id', $frame->id)
            ->where('visuals.frames.0.url', route('records.frames.image', [$record, $frame]))
            ->where('visuals.frames.0.ocr_text', 'Regional S&T Fair 2026')
            ->where('visuals.frames.0.face_count', 1)
            ->where('visuals.thumbnail', null));
    }

    /**
     * A real 1x1 PNG (fake images need the GD extension, which isn't installed everywhere).
     */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true),
        );
    }

    /**
     * A draft record with a probed 60-second master and one analysed frame
     * whose image is on the (faked) media disk.
     *
     * @return array{0: Record, 1: RecordFrame}
     */
    private function recordWithFrame(?RecordFactory $factory = null): array
    {
        $record = ($factory ?? Record::factory())->create();
        RecordFile::factory()->for($record)->create()->technicalMetadata()->create(['duration' => 60]);
        $frame = RecordFrame::factory()->analysed()->for($record)->create([
            'time_sec' => 12.5,
            'path' => "records/{$record->id}/frames/000_12500.jpg",
        ]);
        Storage::disk('media')->put($frame->path, 'frame image');

        return [$record, $frame];
    }
}
