<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\MetadataSource;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Enums\SubjectType;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_all_confirms_every_pending_subject_and_keyword_in_one_audit_entry(): void
    {
        $record = $this->recordWithSuggestions();

        $this->actingAs($record->creator)
            ->postJson(route('records.suggestions.confirm', $record))
            ->assertOk()
            ->assertJsonPath('confirmed', 2);

        $this->assertSame(0, $record->subjects()->where('is_confirmed', false)->count());
        $this->assertSame(0, $record->keywords()->where('is_confirmed', false)->count());
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Updated->value,
            'remarks' => 'Subjects confirmed: Climate. Keywords confirmed: drought.',
        ]);
    }

    public function test_another_encoder_cannot_confirm_and_the_owner_cannot_once_submitted(): void
    {
        $record = $this->recordWithSuggestions();

        $this->actingAs(User::factory()->encoder()->create())
            ->postJson(route('records.suggestions.confirm', $record))
            ->assertForbidden();

        $record->forceFill(['review_status' => ReviewStatus::Submitted])->save();
        $this->actingAs($record->creator)
            ->postJson(route('records.suggestions.confirm', $record))
            ->assertForbidden();

        $this->assertSame(1, $record->keywords()->where('is_confirmed', false)->count());
    }

    public function test_an_admin_can_confirm_them_on_a_submitted_record(): void
    {
        $record = $this->recordWithSuggestions();
        $record->forceFill(['review_status' => ReviewStatus::Submitted])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('records.suggestions.confirm', $record))
            ->assertOk()
            ->assertJsonPath('confirmed', 2);
    }

    public function test_submitting_can_confirm_the_suggestions_too(): void
    {
        $record = $this->recordWithSuggestions();

        $this->actingAs($record->creator)
            ->postJson(route('records.submit', $record), ['confirm_suggestions' => true])
            ->assertOk();

        $this->assertSame(ReviewStatus::Submitted, $record->refresh()->review_status);
        $this->assertSame(0, $record->subjects()->where('is_confirmed', false)->count());
    }

    public function test_submitting_without_the_option_leaves_them_unconfirmed(): void
    {
        $record = $this->recordWithSuggestions();

        $this->actingAs($record->creator)
            ->postJson(route('records.submit', $record), ['confirm_suggestions' => false])
            ->assertOk();

        $this->assertSame(1, $record->subjects()->where('is_confirmed', false)->count());
    }

    private function recordWithSuggestions(): Record
    {
        $record = Record::factory()->create(['processing_status' => ProcessingStatus::Ready]);
        $record->subjects()->create(['subject' => 'Climate', 'type' => SubjectType::Topic, 'source' => MetadataSource::Ai, 'is_confirmed' => false]);
        $record->subjects()->create(['subject' => 'Already kept', 'type' => SubjectType::Topic, 'source' => MetadataSource::Manual, 'is_confirmed' => true]);
        $record->keywords()->create(['keyword' => 'drought', 'source' => MetadataSource::Ai, 'is_confirmed' => false]);

        return $record;
    }
}
