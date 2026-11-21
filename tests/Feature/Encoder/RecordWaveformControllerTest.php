<?php

namespace Tests\Feature\Encoder;

use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecordWaveformControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_gets_the_waveform_and_the_workspace_links_to_it(): void
    {
        Storage::fake('media');
        $record = Record::factory()->create();
        Storage::disk('media')->put("records/{$record->id}/waveform.json", '{"version":1,"peaks_per_second":10,"peaks":[0,50,100]}');

        $this->actingAs($record->creator)
            ->get(route('records.waveform', $record))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $this->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page->where('media.has_waveform', true));
    }

    public function test_a_record_without_a_waveform_yet_returns_404_and_no_link(): void
    {
        Storage::fake('media');
        $record = Record::factory()->create();

        $this->actingAs($record->creator)->get(route('records.waveform', $record))->assertNotFound();
        $this->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page->where('media.has_waveform', false));
    }

    public function test_another_encoder_cannot_read_the_waveform(): void
    {
        Storage::fake('media');
        $record = Record::factory()->create();
        Storage::disk('media')->put("records/{$record->id}/waveform.json", '{}');

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.waveform', $record))
            ->assertForbidden();
    }
}
