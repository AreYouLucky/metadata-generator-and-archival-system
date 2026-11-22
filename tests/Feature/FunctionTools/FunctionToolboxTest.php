<?php

namespace Tests\Feature\FunctionTools;

use App\Http\Controllers\FunctionTools\FunctionToolbox;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\RecordTranscript;
use App\Models\User;
use App\Services\Chat\ChatScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The chat's function tools read only the record they are given, and say
 * plainly when the record lacks the data.
 */
class FunctionToolboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tool_has_a_strict_schema(): void
    {
        foreach (app(FunctionToolbox::class)->definitions() as $tool) {
            $properties = array_keys((array) $tool['parameters']['properties']);

            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,63}$/', $tool['name']);
            $this->assertFalse($tool['parameters']['additionalProperties'], $tool['name']);
            $this->assertSame($properties, $tool['parameters']['required'], $tool['name']);
        }
    }

    public function test_search_finds_passages_by_meaning_across_languages(): void
    {
        config(['archive.embedding_dimensions' => 3]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/embed' => fn (Request $request) => Http::response([
            'model' => 'text-embedding-3-small',
            'dimensions' => 3,
            // "budget" and the Filipino "pondo" mean the same here; everything else doesn't.
            'vectors' => array_map(fn (string $text): array => preg_match('/budget|pondo/i', $text) ? [1.0, 0.0, 0.0] : [0.0, 1.0, 0.0], $request['texts']),
            'usage' => [],
        ])]);
        $record = Record::factory()->approved()->create();
        $this->transcript($record, [
            [0.0, 50.0, 'Magandang umaga sa lahat.'],
            [50.0, 100.0, 'Ang pondo para sa proyekto ay dalawang milyon.'],
        ]);

        $result = app(FunctionToolbox::class)->run($this->scope($record), 'search_transcript', ['record_id' => $record->id, 'query' => 'budget', 'limit' => 3]);

        $this->assertTrue($result->available);
        $this->assertSame('semantic', $result->data['method']);
        $this->assertSame([50.0], array_column($result->data['passages'], 'start'));
        $this->assertSame([['record_id' => $record->id, 'start' => 50.0, 'end' => 100.0]], $result->moments);
    }

    public function test_only_changed_passages_are_embedded_again_after_an_edit(): void
    {
        config(['archive.embedding_dimensions' => 3]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/embed' => fn (Request $request) => Http::response([
            'model' => 'text-embedding-3-small',
            'vectors' => array_fill(0, count($request['texts']), [0.0, 0.0, 1.0]),
            'usage' => [],
        ])]);
        $record = Record::factory()->approved()->create();
        $segments = $this->transcript($record, [[0.0, 50.0, 'Opening remarks.'], [50.0, 100.0, 'The plan for next year.']]);
        $toolbox = app(FunctionToolbox::class);
        $toolbox->run($this->scope($record), 'search_transcript', ['record_id' => $record->id, 'query' => 'plan']);
        $segments[1]->update(['text' => 'The budget for next year.']);

        $toolbox->run($this->scope($record), 'search_transcript', ['record_id' => $record->id, 'query' => 'budget']);

        $embedded = Http::recorded()->map(fn (array $pair) => $pair[0]['texts'])->values()->all();
        $this->assertSame([['plan'], ['Opening remarks.', 'The plan for next year.'], ['budget'], ['The budget for next year.']], $embedded);
        $this->assertSame(2, $record->transcriptChunks()->count());
    }

    public function test_read_transcript_returns_the_lines_in_range_with_speaker_names(): void
    {
        $record = Record::factory()->approved()->create();
        $speaker = RecordSpeaker::factory()->for($record)->create(['label' => 'S1', 'display_name' => 'Mayor Cruz']);
        $segments = $this->transcript($record, [[0.0, 5.0, 'Welcome.'], [65.0, 70.0, 'Thank you all.'], [400.0, 405.0, 'Goodbye.']]);
        $segments[1]->update(['record_speaker_id' => $speaker->id]);

        $result = app(FunctionToolbox::class)->run($this->scope($record), 'read_transcript', ['record_id' => $record->id, 'start_seconds' => 60, 'end_seconds' => 120]);

        $this->assertSame([['start' => 65.0, 'end' => 70.0, 'time' => '1:05', 'speaker' => 'Mayor Cruz', 'text' => 'Thank you all.']], $result->data['lines']);
    }

    public function test_tools_say_when_the_record_lacks_the_data(): void
    {
        $record = Record::factory()->approved()->create();
        $toolbox = app(FunctionToolbox::class);

        $this->assertSame('This record has no transcript yet (it may still be processing, or nothing was said).', $toolbox->run($this->scope($record), 'search_transcript', ['record_id' => $record->id, 'query' => 'budget'])->data['reason']);
        $this->assertSame('Visual analysis has not been run for this record (no key frames).', $toolbox->run($this->scope($record), 'get_visual_content', ['record_id' => $record->id])->data['reason']);
        $this->assertSame('No AI metadata has been generated for this record yet.', $toolbox->run($this->scope($record), 'get_generated_metadata', ['record_id' => $record->id])->data['reason']);
        $this->assertFalse($toolbox->run($this->scope($record), 'get_speakers', ['record_id' => $record->id])->available);
    }

    public function test_visual_content_reads_only_this_records_frames_and_never_identifies_faces(): void
    {
        $record = Record::factory()->approved()->create();
        RecordFrame::factory()->analysed()->for($record)->create(['time_sec' => 12.0]);
        RecordFrame::factory()->analysed()->for(Record::factory()->approved())->create(['ocr_text' => 'Another record']);

        $result = app(FunctionToolbox::class)->run($this->scope($record), 'get_visual_content', ['record_id' => $record->id, 'start_seconds' => null, 'end_seconds' => null]);

        $this->assertSame([[
            'start' => 12.0,
            'time' => '0:12',
            'description' => 'A speaker at a podium in front of a banner.',
            'on_screen_text' => 'Regional S&T Fair 2026',
            'objects' => ['person'],
            'faces' => 1,
        ]], $result->data['key_frames']);
    }

    public function test_speakers_are_counted_from_their_lines(): void
    {
        $record = Record::factory()->approved()->create();
        $speaker = RecordSpeaker::factory()->for($record)->create(['label' => 'S1', 'display_name' => null]);
        $segments = $this->transcript($record, [[10.0, 14.0, 'Hello.'], [20.0, 26.0, 'Again.']]);
        RecordSegment::query()->whereKey(array_map(fn (RecordSegment $segment): int => $segment->id, $segments))->update(['record_speaker_id' => $speaker->id]);

        $result = app(FunctionToolbox::class)->run($this->scope($record), 'get_speakers', ['record_id' => $record->id]);

        $this->assertSame([['label' => 'S1', 'name' => null, 'lines' => 2, 'seconds_speaking' => 10.0, 'first_speaks_at' => '0:10']], $result->data['speakers']);
    }

    public function test_the_scope_is_approved_records_the_account_may_see(): void
    {
        $encoder = User::factory()->encoder()->create();
        $ownApproved = Record::factory()->approved()->for($encoder, 'creator')->create();
        $ownDraft = Record::factory()->for($encoder, 'creator')->create();
        $public = Record::factory()->approved()->create();
        $confidential = Record::factory()->approved()->create(['security_classification' => 'confidential']);
        $submitted = Record::factory()->submitted()->create();
        $visible = fn (User $user): array => (new ChatScope($user))->records()->orderBy('id')->pluck('id')->all();
        $sorted = function (Record ...$records): array {
            $ids = array_map(fn (Record $record): string => $record->id, $records);
            sort($ids);

            return $ids;
        };

        $this->assertSame($sorted($ownApproved, $public, $confidential), $visible(User::factory()->admin()->create()));
        $this->assertSame($sorted($ownApproved, $public), $visible($encoder));
        $this->assertSame($sorted($ownApproved, $public), $visible(User::factory()->create()));
        $this->assertSame([], $visible(User::factory()->inactive()->create()));
        $this->assertTrue((new ChatScope($encoder))->seesInternal($ownApproved));
        $this->assertFalse((new ChatScope($encoder))->seesInternal($public));
        $this->assertNull((new ChatScope($encoder))->find($ownDraft->id));
        $this->assertNull((new ChatScope($encoder))->find($submitted->id));
    }

    private function scope(Record $record): ChatScope
    {
        return new ChatScope($record->creator);
    }

    /**
     * @param  list<array{0: float, 1: float, 2: string}>  $lines
     * @return list<RecordSegment>
     */
    private function transcript(Record $record, array $lines): array
    {
        $transcript = RecordTranscript::factory()->for($record)->create();
        $segments = [];

        foreach ($lines as $position => [$start, $end, $text]) {
            $segments[] = RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => $position, 'start' => $start, 'end' => $end, 'text' => $text, 'original_text' => $text]);
        }

        return $segments;
    }
}
