<?php

namespace Tests\Feature\Models;

use App\Enums\MediaFileRole;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\SourceType;
use App\Models\MediaFile;
use App\Models\Project;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_project_starts_as_a_queued_draft_before_it_is_refreshed(): void
    {
        $encoder = User::factory()->encoder()->create();

        $project = $encoder->projects()->create([
            'title' => 'Board meeting',
            'source_type' => SourceType::Upload,
        ]);

        $this->assertSame(ProjectStatus::Queued, $project->status);
        $this->assertSame(ReviewStatus::Draft, $project->review_status);
    }

    public function test_workflow_fields_cannot_be_mass_assigned(): void
    {
        $encoder = User::factory()->encoder()->create();

        $project = $encoder->projects()->create([
            'title' => 'Board meeting',
            'source_type' => SourceType::Upload,
            'review_status' => ReviewStatus::Approved,
            'published_at' => now(),
        ]);

        $project->refresh();
        $this->assertSame(ReviewStatus::Draft, $project->review_status);
        $this->assertNull($project->published_at);
    }

    public function test_master_file_returns_only_the_master_version(): void
    {
        $project = Project::factory()->create();
        MediaFile::factory()->for($project)->create(['role' => MediaFileRole::Proxy]);
        $master = MediaFile::factory()->for($project)->create(['role' => MediaFileRole::Master]);

        $this->assertTrue($project->masterFile?->is($master));
    }

    public function test_permanently_deleting_a_project_removes_its_transcript(): void
    {
        $project = Project::factory()->create();
        $segment = TranscriptSegment::factory()->for($project)->create();

        $project->forceDelete();

        $this->assertModelMissing($segment);
    }
}
