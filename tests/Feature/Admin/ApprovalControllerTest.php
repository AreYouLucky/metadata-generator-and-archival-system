<?php

namespace Tests\Feature\Admin;

use App\Enums\AccessLevel;
use App\Enums\AuditAction;
use App\Enums\PermissionStatus;
use App\Enums\ReviewStatus;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\RecordRights;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_queue_lists_submitted_records_oldest_first(): void
    {
        $newer = Record::factory()->submitted()->create(['submitted_at' => now()->subDay()]);
        $older = Record::factory()->submitted()->create(['submitted_at' => now()->subDays(3)]);
        Record::factory()->approved()->create();
        Record::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.approvals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/approvals/index-page/index')
                ->has('records.data', 2)
                ->where('records.data.0.id', $older->id)
                ->where('records.data.1.id', $newer->id)
                ->where('records.data.0.creator', $older->creator->name));
    }

    public function test_encoders_cannot_open_the_queue_or_decide(): void
    {
        $record = Record::factory()->submitted()->create();

        $this->actingAs($record->creator)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($record->creator)
            ->postJson(route('admin.approvals.store', $record), ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame(ReviewStatus::Submitted, $record->refresh()->review_status);
    }

    public function test_the_review_page_says_what_would_keep_the_record_off_the_public_site(): void
    {
        $record = Record::factory()->submitted()->create();
        RecordRights::factory()->for($record)->create(['access_level' => AccessLevel::Internal, 'permission_status' => PermissionStatus::Approved]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.approvals.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/approvals/show-page/show')
                ->where('record.id', $record->id)
                ->where('canReview', true)
                ->where('publicBlockers', ['Its access level is internal, not public.']));
    }

    public function test_the_review_page_shows_the_frames_with_faces_for_a_consent_check(): void
    {
        $record = Record::factory()->submitted()->create();
        RecordFrame::factory()->for($record)->create(['time_sec' => 2, 'face_count' => 0]);
        $first = RecordFrame::factory()->for($record)->create(['time_sec' => 5, 'face_count' => 2]);
        RecordFrame::factory()->for($record)->create(['time_sec' => 9, 'face_count' => 1]);
        RecordFrame::factory()->for($record)->create(['time_sec' => 12, 'face_count' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.approvals.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->where('faces.counted_frames', 3)
                ->where('faces.frames_with_faces', 2)
                ->where('faces.release_consent', 'not_available')
                ->where('faces.frames.0.id', $first->id)
                ->where('faces.frames.0.face_count', 2)
                ->has('faces.frames', 2));
    }

    public function test_the_review_page_has_no_face_check_when_faces_were_never_counted(): void
    {
        $record = Record::factory()->submitted()->create();
        RecordFrame::factory()->for($record)->create(['face_count' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.approvals.show', $record))
            ->assertInertia(fn (Assert $page) => $page->where('faces', null));
    }

    public function test_the_review_page_includes_everything_from_the_workspace(): void
    {
        $record = Record::factory()->submitted()->create(['language' => 'en', 'language_confidence' => 0.97]);
        $transcript = RecordTranscript::factory()->for($record)->create(['language' => 'en']);
        RecordSegment::factory()->for($transcript, 'transcript')->create(['text' => 'Good morning.']);
        RecordTranscript::factory()->for($record)->translation('fil')->create();
        $record->metadata()->create([
            'preset' => 'generic',
            'model' => 'test-model',
            'payload' => ['titles' => ['A title'], 'description' => 'AI description', 'chapters' => [['start' => 0, 'title' => 'Intro']]],
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.approvals.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->where('record.language_confidence', 0.97)
                ->where('transcript.language', 'en')
                ->where('transcript.segments.0.text', 'Good morning.')
                ->where('metadata.payload.description', 'AI description')
                ->where('metadata.payload.chapters.0.title', 'Intro')
                ->where('translations.0.language', 'fil')
                ->has('archive.descriptive')
                ->has('archive.files')
                ->has('speakers'));
    }

    public function test_approving_publishes_the_record_and_logs_the_decision(): void
    {
        $admin = User::factory()->admin()->create();
        $record = Record::factory()->submitted()->create();
        RecordRights::factory()->for($record)->publicAccess()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.approvals.store', $record), ['action' => 'approve', 'notes' => 'Looks good.'])
            ->assertOk()
            ->assertJsonPath('review_status', 'approved');

        $record->refresh();
        $this->assertSame(ReviewStatus::Approved, $record->review_status);
        $this->assertSame($admin->id, $record->reviewed_by);
        $this->assertNotNull($record->reviewed_at);
        $this->assertNotNull($record->published_at);
        $this->assertTrue(Gate::forUser(null)->allows('distribute', $record));
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Approved->value,
            'performed_by' => $admin->id,
            'remarks' => 'Looks good.',
        ]);
    }

    public function test_requesting_changes_needs_a_note_and_gives_the_record_back_to_the_encoder(): void
    {
        $admin = User::factory()->admin()->create();
        $record = Record::factory()->submitted()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.approvals.store', $record), ['action' => 'request_changes'])
            ->assertJsonValidationErrors(['notes' => 'Add a note so the encoder knows what to change.']);

        $this->actingAs($admin)
            ->postJson(route('admin.approvals.store', $record), ['action' => 'request_changes', 'notes' => 'Fix the date.'])
            ->assertOk();

        $record->refresh();
        $this->assertSame(ReviewStatus::ChangesRequested, $record->review_status);
        $this->assertNull($record->published_at);
        $this->assertTrue($record->creator->can('update', $record));
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::ChangesRequested->value,
            'remarks' => 'Fix the date.',
        ]);
    }

    public function test_rejecting_needs_a_note_and_leaves_the_record_read_only(): void
    {
        $record = Record::factory()->submitted()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.approvals.store', $record), ['action' => 'reject', 'notes' => 'Duplicate of another record.'])
            ->assertOk();

        $record->refresh();
        $this->assertSame(ReviewStatus::Rejected, $record->review_status);
        $this->assertFalse($record->creator->can('update', $record));
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $record = Record::factory()->submitted()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.approvals.store', $record), ['action' => 'publish'])
            ->assertJsonValidationErrors('action');
    }

    public function test_a_record_that_is_not_waiting_cannot_be_decided(): void
    {
        $record = Record::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.approvals.store', $record), ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame(ReviewStatus::Draft, $record->refresh()->review_status);
    }

    public function test_reopening_an_approved_record_unpublishes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $record = Record::factory()->approved()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.records.reopen', $record), ['notes' => 'Wrong licence.'])
            ->assertOk()
            ->assertJsonPath('review_status', 'draft');

        $record->refresh();
        $this->assertSame(ReviewStatus::Draft, $record->review_status);
        $this->assertNull($record->published_at);
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Reopened->value,
            'performed_by' => $admin->id,
            'remarks' => 'Wrong licence.',
        ]);
    }

    public function test_only_approved_or_rejected_records_can_be_reopened_and_only_by_admins(): void
    {
        $draft = Record::factory()->create();
        $approved = Record::factory()->approved()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.records.reopen', $draft))
            ->assertForbidden();
        $this->actingAs($approved->creator)
            ->postJson(route('admin.records.reopen', $approved))
            ->assertForbidden();

        $this->assertSame(ReviewStatus::Approved, $approved->refresh()->review_status);
    }

    public function test_the_access_log_link_opens_the_logs_filtered_to_the_record(): void
    {
        $record = Record::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.records.access-log', $record))
            ->assertRedirect(route('admin.logs.index', ['type' => 'access', 'record' => $record->id]));
    }
}
