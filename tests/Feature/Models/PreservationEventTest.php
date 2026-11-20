<?php

namespace Tests\Feature\Models;

use App\Enums\EventOutcome;
use App\Models\PreservationEvent;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PreservationEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_event_throws_and_leaves_the_row_unchanged(): void
    {
        $event = PreservationEvent::factory()->create(['outcome' => EventOutcome::Success]);

        try {
            $event->update(['outcome' => EventOutcome::Failure]);
            $this->fail('Updating a preservation event should throw.');
        } catch (LogicException) {
            $this->assertSame(EventOutcome::Success, $event->fresh()?->outcome);
        }
    }

    public function test_deleting_an_event_throws_and_keeps_the_row(): void
    {
        $event = PreservationEvent::factory()->create();

        try {
            $event->delete();
            $this->fail('Deleting a preservation event should throw.');
        } catch (LogicException) {
            $this->assertModelExists($event);
        }
    }

    public function test_events_survive_when_their_project_is_permanently_deleted(): void
    {
        $project = Project::factory()->create();
        $event = PreservationEvent::factory()->create(['project_id' => $project->id]);

        $project->forceDelete();

        $this->assertModelMissing($project);
        $this->assertSame($project->id, $event->fresh()?->project_id);
    }
}
