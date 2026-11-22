<?php

namespace Tests\Feature\Encoder;

use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordFrame;
use Database\Factories\RecordFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Encoders add, correct and remove key frames in the workspace.
 */
class RecordFrameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_frame_at_the_players_time_is_captured_and_added(): void
    {
        Storage::fake('media');
        [$record] = $this->recordWithFrame();
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/frames/capture' => function (Request $request) {
            Storage::disk('media')->put($request['output_path'], 'captured');

            return Http::response(['path' => $request['output_path']]);
        }]);

        $this->actingAs($record->creator)
            ->postJson(route('records.frames.store', $record), ['time_sec' => 30.25, 'description' => '  The host waves.  '])
            ->assertCreated()
            ->assertJsonPath('time_sec', 30.25)
            ->assertJsonPath('description', 'The host waves.')
            ->assertJsonPath('face_count', null);

        $frame = $record->frames()->where('time_sec', 30.25)->sole();
        $this->assertStringStartsWith("records/{$record->id}/frames/", $frame->path);
        $this->assertSame('captured', Storage::disk('media')->get($frame->path));
        Http::assertSent(fn (Request $request): bool => $request['output_path'] === $frame->path && $request['time_sec'] === 30.25);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'remarks' => 'Key frame added at 0:30.']);
    }

    public function test_a_second_frame_at_the_same_time_or_past_the_end_is_refused(): void
    {
        [$record] = $this->recordWithFrame();
        Http::preventStrayRequests();

        $this->actingAs($record->creator)
            ->postJson(route('records.frames.store', $record), ['time_sec' => 12.5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['time_sec' => 'There is already a key frame at this time.']);
        $this->postJson(route('records.frames.store', $record), ['time_sec' => 61])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['time_sec' => 'That time is past the end of the video.']);

        $this->assertSame(1, $record->frames()->count());
    }

    public function test_the_description_and_on_screen_text_are_corrected_and_logged(): void
    {
        [$record, $frame] = $this->recordWithFrame();

        $this->actingAs($record->creator)
            ->patchJson(route('records.frames.update', [$record, $frame]), ['description' => ' Mayor at the podium. ', 'ocr_text' => ''])
            ->assertOk()
            ->assertJsonPath('id', $frame->id)
            ->assertJsonPath('description', 'Mayor at the podium.')
            ->assertJsonPath('ocr_text', null);

        $frame->refresh();
        $this->assertSame('Mayor at the podium.', $frame->description);
        $this->assertNull($frame->ocr_text);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'remarks' => 'Key frame at 0:12 edited: description, text on screen.']);
    }

    public function test_removing_a_frame_deletes_its_image_and_keeps_the_thumbnail_copy(): void
    {
        Storage::fake('media');
        [$record, $frame] = $this->recordWithFrame();
        $record->thumbnail_path = "records/{$record->id}/thumbnail/copy.jpg";
        $record->thumbnail_frame_id = $frame->id;
        $record->save();
        Storage::disk('media')->put($record->thumbnail_path, 'copy');

        $this->actingAs($record->creator)
            ->deleteJson(route('records.frames.destroy', [$record, $frame]))
            ->assertNoContent();

        $this->assertModelMissing($frame);
        Storage::disk('media')->assertMissing($frame->path);
        $record->refresh();
        $this->assertNull($record->thumbnail_frame_id);
        Storage::disk('media')->assertExists((string) $record->thumbnail_path);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'remarks' => 'Key frame removed at 0:12.']);
    }

    public function test_a_frame_is_only_reachable_through_its_own_record(): void
    {
        [$record, $frame] = $this->recordWithFrame();
        $other = Record::factory()->create(['created_by' => $record->created_by]);

        $this->actingAs($record->creator)
            ->patchJson(route('records.frames.update', [$other, $frame]), ['description' => 'x'])
            ->assertNotFound();
        $this->deleteJson(route('records.frames.destroy', [$other, $frame]))->assertNotFound();

        $this->assertModelExists($frame);
    }

    public function test_an_approved_record_is_read_only_for_its_encoder(): void
    {
        [$record, $frame] = $this->recordWithFrame(Record::factory()->approved());
        Http::preventStrayRequests();

        $this->actingAs($record->creator)->postJson(route('records.frames.store', $record), ['time_sec' => 5])->assertForbidden();
        $this->patchJson(route('records.frames.update', [$record, $frame]), ['description' => 'x'])->assertForbidden();
        $this->deleteJson(route('records.frames.destroy', [$record, $frame]))->assertForbidden();

        $this->assertModelExists($frame);
    }

    /**
     * A draft record with a probed 60-second master and one analysed frame
     * at 12.5 s whose image is on the media disk.
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
