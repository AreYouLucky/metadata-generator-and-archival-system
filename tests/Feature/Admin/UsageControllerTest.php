<?php

namespace Tests\Feature\Admin;

use App\Enums\UsageKind;
use App\Models\Record;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_is_summed_per_encoder_and_record_within_the_range(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));
        $record = Record::factory()->create();
        $this->usage($record, UsageKind::TranscriptionMinutes, 12.5, 0.075, '2026-11-10 09:00');
        $this->usage($record, UsageKind::LlmInputTokens, 4000, 0.01, '2026-11-10 09:05');
        $this->usage($record, UsageKind::TranscriptionMinutes, 99, 1.0, '2026-10-31 23:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.usage.index', ['from' => '2026-11-01', 'to' => '2026-11-20']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/usage-page/usage')
                ->where('totals.minutes', 12.5)
                ->where('totals.input_tokens', 4000)
                ->where('totals.cost', 0.085)
                ->where('encoders.0.name', $record->creator->name)
                ->where('encoders.0.minutes', 12.5)
                ->where('records.data.0.record_id', $record->id)
                ->has('daily', 20)
                ->where('daily.9.date', '2026-11-10')
                ->where('daily.9.minutes', 12.5));
    }

    public function test_the_range_defaults_to_this_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.usage.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-11-01')
                ->where('filters.to', '2026-11-20'));
    }

    public function test_an_end_date_before_the_start_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.usage.index', ['from' => '2026-11-10', 'to' => '2026-11-01']))
            ->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
    }

    public function test_the_csv_lists_records_and_neutralises_spreadsheet_formulas(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00'));
        $record = Record::factory()->create(['title' => '=HYPERLINK("http://evil")']);
        $this->usage($record, UsageKind::TranscriptionMinutes, 3, 0.02, '2026-11-15 10:00');

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.usage.export', ['from' => '2026-11-01', 'to' => '2026-11-20']))
            ->assertOk()
            ->assertDownload('usage-2026-11-01-to-2026-11-20.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('record_id,accession_number,title,encoder,transcription_minutes', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_encoders_cannot_see_usage(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('admin.usage.index'))
            ->assertForbidden();
    }

    public function test_the_overview_counts_pending_approvals(): void
    {
        Record::factory()->submitted()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.overview'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/overview-page/overview')
                ->where('pending.count', 2)
                ->has('pending.oldest', 2));
    }

    private function usage(Record $record, UsageKind $kind, float $quantity, float $cost, string $at): void
    {
        UsageRecord::create([
            'user_id' => $record->created_by,
            'record_id' => $record->id,
            'kind' => $kind,
            'quantity' => $quantity,
            'estimated_cost' => $cost,
            'occurred_at' => Carbon::parse($at),
        ]);
    }
}
