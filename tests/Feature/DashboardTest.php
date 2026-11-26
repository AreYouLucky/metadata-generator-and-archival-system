<?php

namespace Tests\Feature;

use App\Enums\PipelineStage;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Enums\StageStatus;
use App\Models\Project;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_public_viewers_are_sent_to_the_catalogue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('catalogue.home'));
    }

    public function test_encoders_see_only_their_own_records(): void
    {
        $encoder = User::factory()->encoder()->create();
        Record::factory()->for($encoder, 'creator')->count(2)->create();
        Record::factory()->for($encoder, 'creator')->submitted()->create();
        Record::factory()->approved()->count(4)->create();

        $response = $this->actingAs($encoder)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('encoder/dashboard-page/dashboard')
            ->where('isAdmin', false)
            ->where('stats.total', 3)
            ->where('stats.added_this_week', 3)
            ->where('stats.review.draft', 2)
            ->where('stats.review.submitted', 1)
            ->where('stats.review.approved', 0)
            ->where('activity.29.count', 3));
    }

    public function test_admins_see_every_record_with_its_encoder(): void
    {
        $encoder = User::factory()->encoder()->create(['name' => 'Ana Encoder']);
        Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Failed]);
        Record::factory()->approved()->count(2)->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', true)
            ->where('stats.total', 3)
            ->where('stats.review.approved', 2)
            ->where('attention.0.creator', 'Ana Encoder')
            ->where('allowance', null));
    }

    public function test_attention_lists_failed_processing_and_requested_changes_only(): void
    {
        $encoder = User::factory()->encoder()->create();
        $failed = Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Failed]);
        $returned = Record::factory()->for($encoder, 'creator')->create([
            'processing_status' => ProcessingStatus::Ready,
            'review_status' => ReviewStatus::ChangesRequested,
            'updated_at' => now()->addMinute(),
        ]);
        Record::factory()->for($encoder, 'creator')->submitted()->create();

        $response = $this->actingAs($encoder)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('attention', 2)
            ->where('attention.0.id', $returned->id)
            ->where('attention.0.reason', 'changes_requested')
            ->where('attention.0.creator', null)
            ->where('attention.1.id', $failed->id)
            ->where('attention.1.reason', 'failed'));
    }

    public function test_processing_shows_the_running_stage_and_overall_progress(): void
    {
        $encoder = User::factory()->encoder()->create();
        $record = Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Processing]);
        $record->stages()->createMany([
            ['stage' => PipelineStage::Probe, 'status' => StageStatus::Done, 'progress' => 100],
            ['stage' => PipelineStage::Transcribe, 'status' => StageStatus::Running, 'progress' => 40],
            ['stage' => PipelineStage::GenerateMetadata, 'status' => StageStatus::Pending, 'progress' => 0],
            ['stage' => PipelineStage::Scenes, 'status' => StageStatus::Skipped, 'progress' => 0],
        ]);
        Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Ready]);

        $response = $this->actingAs($encoder)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('processing', 1)
            ->where('processing.0.id', $record->id)
            ->where('processing.0.stage', 'Transcription')
            ->where('processing.0.progress', 47));
    }

    public function test_drafts_list_only_processed_drafts(): void
    {
        $encoder = User::factory()->encoder()->create();
        $ready = Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Ready]);
        Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Queued]);
        Record::factory()->for($encoder, 'creator')->submitted()->create();

        $response = $this->actingAs($encoder)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('drafts', 1)
            ->where('drafts.0.id', $ready->id));
    }

    public function test_footage_and_projects_count_only_the_encoders_records(): void
    {
        $encoder = User::factory()->encoder()->create();
        $project = Project::factory()->create();
        $own = Record::factory()->for($encoder, 'creator')->for($project)->create();
        $other = Record::factory()->for($project)->create();
        $this->master($own, 90.5);
        $this->master($other, 600);

        $response = $this->actingAs($encoder)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('stats.duration_seconds', 90.5)
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.records_count', 1));
    }

    public function test_encoders_see_todays_processing_allowance(): void
    {
        $encoder = User::factory()->encoder()->create(['daily_processing_minutes_limit' => 60]);

        $response = $this->actingAs($encoder)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('allowance.limit', 60)
            ->where('allowance.used', 0)
            ->where('allowance.remaining', 60));
    }

    private function master(Record $record, float $duration): void
    {
        $file = RecordFile::factory()->for($record)->create();
        $file->technicalMetadata()->create(['duration' => $duration]);
    }
}
