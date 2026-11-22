<?php

namespace Tests\Feature\Encoder;

use App\Enums\ProcessingStatus;
use App\Enums\UsageKind;
use App\Jobs\StartProcessingJob;
use App\Models\Project;
use App\Models\Record;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Daily processing limits (PROJECT_SPEC §14), checked before processing
 * starts: on a new record and on a retry.
 */
class ProcessingLimitTest extends TestCase
{
    use RefreshDatabase;

    private const string LIMIT_MESSAGE = "You have used today's processing limit (30 minutes). Try again tomorrow, or ask an admin to raise your limit.";

    public function test_an_encoder_over_the_daily_limit_cannot_start_a_new_record(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $encoder = $this->encoderWithLimit(30);
        $this->usedToday($encoder, 30);

        $this->actingAs($encoder)
            ->postJson(route('records.store'), [
                'upload_id' => fake()->uuid(),
                'project_id' => Project::factory()->create()->id,
                'preset' => 'generic',
            ])
            ->assertJsonValidationErrors(['daily_limit' => self::LIMIT_MESSAGE]);

        Queue::assertNothingPushed();
    }

    public function test_an_encoder_over_the_daily_limit_cannot_retry_processing(): void
    {
        Queue::fake([StartProcessingJob::class]);
        $encoder = $this->encoderWithLimit(30);
        $this->usedToday($encoder, 31);
        $record = Record::factory()->for($encoder, 'creator')->create(['processing_status' => ProcessingStatus::Failed]);

        $this->actingAs($encoder)
            ->postJson(route('records.retry', $record), ['preset' => 'generic'])
            ->assertJsonValidationErrors(['daily_limit' => self::LIMIT_MESSAGE]);

        $this->assertSame(ProcessingStatus::Failed, $record->refresh()->processing_status);
        Queue::assertNothingPushed();
    }

    public function test_yesterdays_minutes_do_not_count_and_the_page_shows_what_is_left(): void
    {
        $encoder = $this->encoderWithLimit(30);
        $this->usedToday($encoder, 10);
        UsageRecord::create([
            'user_id' => $encoder->id,
            'kind' => UsageKind::TranscriptionMinutes,
            'quantity' => 500,
            'occurred_at' => now()->subDay(),
        ]);

        $this->actingAs($encoder)
            ->get(route('records.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dailyLimit.limit', 30)
                ->where('dailyLimit.used', 10)
                ->where('dailyLimit.remaining', 20));
    }

    public function test_the_configured_default_applies_without_a_personal_limit(): void
    {
        config(['archive.encoder_daily_processing_minutes' => 45]);

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.create'))
            ->assertInertia(fn (Assert $page) => $page->where('dailyLimit.limit', 45));
    }

    public function test_admins_have_no_limit(): void
    {
        $admin = User::factory()->admin()->create();
        $this->usedToday($admin, 10_000);

        $this->actingAs($admin)
            ->get(route('records.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dailyLimit.limit', null)
                ->where('dailyLimit.remaining', null));
    }

    private function encoderWithLimit(int $minutes): User
    {
        $encoder = User::factory()->encoder()->create();
        $encoder->daily_processing_minutes_limit = $minutes;
        $encoder->save();

        return $encoder;
    }

    private function usedToday(User $user, float $minutes): void
    {
        UsageRecord::create([
            'user_id' => $user->id,
            'kind' => UsageKind::TranscriptionMinutes,
            'quantity' => $minutes,
            'occurred_at' => now(),
        ]);
    }
}
