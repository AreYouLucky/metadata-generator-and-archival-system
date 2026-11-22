<?php

namespace Tests\Feature\Encoder;

use App\Enums\SceneKind;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordScene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shots_are_grouped_into_a_named_scene_with_timecodes(): void
    {
        $record = $this->record();

        $this->actingAs($record->creator)
            ->postJson(route('records.scenes.store', $record), ['start' => 4.2, 'end' => 30, 'label' => '  Opening remarks ', 'description' => ''])
            ->assertCreated()
            ->assertJsonPath('label', 'Opening remarks')
            ->assertJsonPath('tc_in', '01:00:04:05')
            ->assertJsonPath('tc_out', '01:00:30:00');

        $scene = $record->scenes()->sole();
        $this->assertSame(SceneKind::Scene, $scene->kind);
        $this->assertNull($scene->description);
    }

    public function test_a_scene_that_ends_before_it_starts_or_has_no_name_is_refused(): void
    {
        $record = $this->record();

        $this->actingAs($record->creator)
            ->postJson(route('records.scenes.store', $record), ['start' => 10, 'end' => 5, 'label' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end' => 'A scene must end after it starts.', 'label' => 'Name the scene.']);
    }

    public function test_a_scene_is_renamed_and_regrouped_and_deleted(): void
    {
        $record = $this->record();
        $scene = RecordScene::factory()->scene()->for($record)->create(['start' => 0, 'end' => 10]);

        $this->actingAs($record->creator)
            ->patchJson(route('records.scenes.update', [$record, $scene]), ['start' => 0, 'end' => 20, 'label' => 'Panel', 'description' => 'Q&A'])
            ->assertOk()
            ->assertJsonPath('tc_out', '01:00:20:00');
        $this->assertSame([20.0, 'Panel', 'Q&A'], [$scene->refresh()->end, $scene->label, $scene->description]);

        $this->deleteJson(route('records.scenes.destroy', [$record, $scene]))->assertNoContent();
        $this->assertModelMissing($scene);
    }

    public function test_a_detected_shot_cannot_be_changed(): void
    {
        $record = $this->record();
        $shot = RecordScene::factory()->for($record)->create(['start' => 0, 'end' => 4]);

        $this->actingAs($record->creator)
            ->deleteJson(route('records.scenes.destroy', [$record, $shot]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Detected shots can\'t be changed; group them into a scene instead.');
        $this->assertModelExists($shot);
    }

    public function test_another_encoder_cannot_group_shots_and_a_scene_is_only_reached_through_its_record(): void
    {
        $record = $this->record();
        $scene = RecordScene::factory()->scene()->for($record)->create();
        $own = Record::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.scenes.store', $record), ['start' => 0, 'end' => 5, 'label' => 'Intro'])
            ->assertForbidden();
        $this->actingAs($own->creator)
            ->deleteJson(route('records.scenes.destroy', [$own, $scene]))
            ->assertNotFound();
        $this->assertModelExists($scene);
    }

    /**
     * A draft record with a 60-second 25 fps master starting at 01:00:00:00.
     */
    private function record(): Record
    {
        $record = Record::factory()->create();
        RecordFile::factory()->for($record)->create()->technicalMetadata()->create([
            'duration' => 60,
            'frame_rate' => '25/1',
            'start_timecode' => '01:00:00:00',
        ]);

        return $record;
    }
}
