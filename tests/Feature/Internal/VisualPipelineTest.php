<?php

namespace Tests\Feature\Internal;

use App\Enums\PipelineStage;
use App\Enums\SceneKind;
use App\Enums\StageStatus;
use App\Enums\UsageKind;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordFrame;
use App\Models\RecordScene;
use App\Models\User;
use App\Services\InternalSignature;
use App\Services\Records\RecordThumbnails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Results of the visual stages (scenes, frames, vision, ocr, objects) on the
 * signed callbacks.
 */
class VisualPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_shots_replace_the_detected_ones_with_timecodes_and_named_scenes_stay(): void
    {
        $record = $this->recordWithStages(frameRate: '25/1', startTimecode: '01:00:00:00');
        RecordScene::factory()->for($record)->create(['start' => 1, 'end' => 2]);
        $scene = RecordScene::factory()->scene()->for($record)->create(['start' => 0, 'end' => 9]);

        $this->postSigned("/internal/records/{$record->id}/results/scenes", [
            'shots' => [['start' => 0, 'end' => 4.2], ['start' => 4.2, 'end' => 9.0]],
        ])->assertOk();

        $shots = $record->scenes()->where('kind', SceneKind::Shot)->get();
        $this->assertSame([[0.0, 4.2, '01:00:00:00', '01:00:04:05'], [4.2, 9.0, '01:00:04:05', '01:00:09:00']], $shots->map(fn (RecordScene $shot): array => [$shot->start, $shot->end, $shot->tc_in, $shot->tc_out])->all());
        $this->assertModelExists($scene);
        $this->assertSame(StageStatus::Done, $this->stageStatus($record, PipelineStage::Scenes));
    }

    public function test_frames_are_stored_and_the_suggested_one_becomes_the_default_thumbnail(): void
    {
        Storage::fake('media');
        $record = $this->recordWithStages();
        $paths = ["records/{$record->id}/frames/000_500.jpg", "records/{$record->id}/frames/001_10000.jpg"];
        Storage::disk('media')->put($paths[0], 'first');
        Storage::disk('media')->put($paths[1], 'second');

        $this->postSigned("/internal/records/{$record->id}/results/frames", [
            'frames' => [
                ['time_sec' => 0.5, 'path' => $paths[0], 'is_scene_start' => true],
                ['time_sec' => 10, 'path' => $paths[1], 'is_scene_start' => false],
            ],
            'thumbnail_index' => 1,
        ])->assertOk();

        $this->assertSame([0.5, 10.0], $record->frames()->pluck('time_sec')->all());
        $record->refresh();
        $this->assertSame(10.0, $record->thumbnail_time_sec);
        $this->assertSame($record->frames()->where('time_sec', 10)->value('id'), $record->thumbnail_frame_id);
        $this->assertStringStartsWith("records/{$record->id}/thumbnail/", (string) $record->thumbnail_path);
        // A copy, so a re-run that replaces the frames keeps the thumbnail.
        $this->assertSame('second', Storage::disk('media')->get((string) $record->thumbnail_path));
        $this->assertDatabaseMissing('video_audit_logs', ['record_id' => $record->id, 'remarks' => 'Thumbnail: key frame at 0:10.']);
    }

    public function test_a_re_run_keeps_the_thumbnail_the_encoder_chose(): void
    {
        Storage::fake('media');
        $record = $this->recordWithStages();
        $record->thumbnail_path = "records/{$record->id}/thumbnail/chosen.jpg";
        $record->save();
        $old = RecordFrame::factory()->for($record)->create(['time_sec' => 3]);
        Storage::disk('media')->put("records/{$record->id}/frames/000_500.jpg", 'new');

        $this->postSigned("/internal/records/{$record->id}/results/frames", [
            'frames' => [['time_sec' => 0.5, 'path' => "records/{$record->id}/frames/000_500.jpg", 'is_scene_start' => true]],
            'thumbnail_index' => 0,
        ])->assertOk();

        $this->assertModelMissing($old);
        $this->assertSame("records/{$record->id}/thumbnail/chosen.jpg", $record->refresh()->thumbnail_path);
    }

    public function test_a_frame_path_outside_the_records_frames_folder_is_refused(): void
    {
        $record = $this->recordWithStages();
        $other = Record::factory()->create();

        $this->postSigned("/internal/records/{$record->id}/results/frames", [
            'frames' => [['time_sec' => 0.5, 'path' => "records/{$other->id}/frames/000_500.jpg", 'is_scene_start' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['frames.0.path']);

        $this->assertSame(0, RecordFrame::query()->count());
    }

    public function test_descriptions_are_matched_to_frames_by_time_and_the_frames_are_counted_as_usage(): void
    {
        $record = $this->recordWithStages();
        $first = RecordFrame::factory()->for($record)->create(['time_sec' => 0.5]);
        $second = RecordFrame::factory()->for($record)->create(['time_sec' => 10]);

        $this->postSigned("/internal/records/{$record->id}/results/vision", [
            'model' => 'gpt-6-luna',
            'frames' => [
                ['time_sec' => 0.5, 'description' => 'A title card.'],
                ['time_sec' => 10, 'description' => null],
            ],
            'usage' => [
                ['kind' => 'llm_input_tokens', 'quantity' => 900, 'estimated_cost' => 0.0001],
                ['kind' => 'vision_frames', 'quantity' => 2, 'estimated_cost' => null],
            ],
        ])->assertOk();

        $this->assertSame('A title card.', $first->refresh()->description);
        $this->assertNull($second->refresh()->description);
        $this->assertDatabaseHas('usage_records', ['record_id' => $record->id, 'kind' => UsageKind::VisionFrames->value, 'quantity' => 2]);
        $this->assertSame(StageStatus::Done, $this->stageStatus($record, PipelineStage::Vision));
    }

    public function test_on_screen_text_objects_and_face_counts_are_stored_per_frame(): void
    {
        $record = $this->recordWithStages();
        $frame = RecordFrame::factory()->for($record)->create(['time_sec' => 12.3]);

        $this->postSigned("/internal/records/{$record->id}/results/ocr", [
            'frames' => [['time_sec' => 12.3, 'text' => "Regional S&T Fair\nCebu City"]],
        ])->assertOk();
        $this->postSigned("/internal/records/{$record->id}/results/objects", [
            'frames' => [['time_sec' => 12.3, 'objects' => [['label' => 'person', 'confidence' => 0.91]], 'face_count' => 2]],
        ])->assertOk();

        $frame->refresh();
        $this->assertSame("Regional S&T Fair\nCebu City", $frame->ocr_text);
        $this->assertSame([['label' => 'person', 'confidence' => 0.91]], $frame->objects);
        $this->assertSame(2, $frame->face_count);
    }

    public function test_a_default_thumbnail_showing_no_one_moves_to_a_frame_with_a_few_faces(): void
    {
        Storage::fake('media');
        $record = $this->recordWithStages();
        $frames = collect([0.5, 4, 8, 15])->map(function (float $time) use ($record): RecordFrame {
            $frame = RecordFrame::factory()->for($record)->create(['time_sec' => $time]);
            Storage::disk('media')->put($frame->path, "frame at {$time}");

            return $frame;
        });
        app(RecordThumbnails::class)->useFrame($record, $frames[1], null);

        $this->postSigned("/internal/records/{$record->id}/results/objects", [
            'frames' => [
                // Before the opening second, no faces, a crowd, then two people.
                ['time_sec' => 0.5, 'objects' => [], 'face_count' => 1],
                ['time_sec' => 4, 'objects' => [], 'face_count' => 0],
                ['time_sec' => 8, 'objects' => [], 'face_count' => 6],
                ['time_sec' => 15, 'objects' => [], 'face_count' => 2],
            ],
        ])->assertOk();

        $record->refresh();
        $this->assertSame($frames[3]->id, $record->thumbnail_frame_id);
        $this->assertTrue($record->thumbnail_is_default);
        $this->assertSame('frame at 15', Storage::disk('media')->get((string) $record->thumbnail_path));
    }

    public function test_counting_faces_keeps_the_thumbnail_an_encoder_chose(): void
    {
        Storage::fake('media');
        $record = $this->recordWithStages();
        $chosen = RecordFrame::factory()->for($record)->create(['time_sec' => 4]);
        $withFaces = RecordFrame::factory()->for($record)->create(['time_sec' => 15]);
        Storage::disk('media')->put($chosen->path, 'chosen');
        Storage::disk('media')->put($withFaces->path, 'faces');
        app(RecordThumbnails::class)->useFrame($record, $chosen, User::factory()->encoder()->create());

        $this->postSigned("/internal/records/{$record->id}/results/objects", [
            'frames' => [
                ['time_sec' => 4, 'objects' => [], 'face_count' => 0],
                ['time_sec' => 15, 'objects' => [], 'face_count' => 2],
            ],
        ])->assertOk();

        $this->assertSame($chosen->id, $record->refresh()->thumbnail_frame_id);
        $this->assertFalse($record->thumbnail_is_default);
    }

    private function recordWithStages(?string $frameRate = null, ?string $startTimecode = null): Record
    {
        $record = Record::factory()->create();
        $master = RecordFile::factory()->for($record)->create();
        $master->technicalMetadata()->create(['frame_rate' => $frameRate, 'start_timecode' => $startTimecode, 'duration' => 60]);
        $record->stages()->createMany(array_map(
            fn (PipelineStage $stage): array => ['stage' => $stage],
            PipelineStage::active(),
        ));

        return $record;
    }

    private function stageStatus(Record $record, PipelineStage $stage): StageStatus
    {
        return $record->stages()->where('stage', $stage)->sole()->status;
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
