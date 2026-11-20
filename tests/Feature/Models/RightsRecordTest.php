<?php

namespace Tests\Feature\Models;

use App\Models\RightsRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RightsRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_is_embargoed_while_the_embargo_date_is_in_the_future(): void
    {
        $record = RightsRecord::factory()->make(['embargo_until' => now()->addMinute()]);

        $this->assertTrue($record->isEmbargoed());
    }

    public function test_record_is_not_embargoed_once_the_embargo_date_has_passed(): void
    {
        $record = RightsRecord::factory()->make(['embargo_until' => now()->subMinute()]);

        $this->assertFalse($record->isEmbargoed());
    }

    public function test_record_without_an_embargo_date_is_not_embargoed(): void
    {
        $record = RightsRecord::factory()->make(['embargo_until' => null]);

        $this->assertFalse($record->isEmbargoed());
    }
}
