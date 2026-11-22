<?php

namespace Tests\Feature\Internal;

use App\Enums\PipelineStage;
use App\Enums\StageStatus;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Services\InternalSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiarizationResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_line_gets_the_speaker_who_talks_longest_during_it(): void
    {
        $record = Record::factory()->create();
        $record->stages()->create(['stage' => PipelineStage::Diarize]);
        $transcript = RecordTranscript::factory()->for($record)->create(['language' => 'en']);
        $lines = collect([[0, 4], [4, 8], [8, 12], [20, 22]])->map(fn (array $span, int $index) => RecordSegment::factory()
            ->for($transcript, 'transcript')
            ->create(['position' => $index + 1, 'start' => $span[0], 'end' => $span[1]]));
        // A name given before a re-run is kept for the same label.
        $host = $record->speakers()->create(['label' => 'S1', 'display_name' => 'Host']);
        $stale = $record->speakers()->create(['label' => 'S9']);

        $this->postSigned("/internal/records/{$record->id}/results/diarize", [
            'provider' => 'pyannote',
            'model' => 'pyannote/speaker-diarization-community-1',
            'turns' => [
                ['start' => 5.0, 'end' => 9.5, 'speaker' => 'SPEAKER_01'],
                ['start' => 0.0, 'end' => 5.0, 'speaker' => 'SPEAKER_00'],
                ['start' => 9.5, 'end' => 12.0, 'speaker' => 'SPEAKER_00'],
            ],
            'usage' => [],
        ])->assertOk();

        $speakers = $record->speakers()->pluck('id', 'label');
        $this->assertSame(['S1', 'S2'], $speakers->keys()->sort()->values()->all());
        $this->assertSame($host->id, $speakers['S1']);
        $this->assertSame('Host', $host->refresh()->display_name);
        $this->assertSame(
            [$speakers['S1'], $speakers['S2'], $speakers['S1'], null],
            $lines->map(fn (RecordSegment $line): ?int => $line->refresh()->record_speaker_id)->all(),
        );
        $this->assertModelMissing($stale);
        $this->assertSame(StageStatus::Done, $record->stages()->sole()->status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(string $uri, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars([
            ...app(InternalSignature::class)->headers($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }
}
