<?php

namespace Tests\Feature\Jobs;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatRole;
use App\Events\ChatAnswered;
use App\Jobs\AnswerChatQuestionJob;
use App\Models\ChatMessage;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The "Ask me" tool loop: the model's tool calls run through the function
 * tools within what the asker may see, and the reply is kept only as far as
 * the tools back it.
 */
class AnswerChatQuestionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tool_backed_answer_keeps_only_citations_the_tools_returned_for_that_record(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $record = Record::factory()->approved()->create(['title' => 'Budget hearing']);
        Http::fake([
            'http://ai-api.test/embed' => Http::response(['detail' => 'No key.'], 502),
            'http://ai-api.test/chat' => Http::sequence()
                ->push($this->toolCalls([['id' => 'c1', 'name' => 'search_transcript', 'arguments' => ['record_id' => $record->id, 'query' => 'budget', 'limit' => null]]]))
                ->push($this->answer('They approved a 2 million peso budget.', true, [
                    ['record_id' => $record->id, 'start' => 3, 'end' => 9],
                    ['record_id' => $record->id, 'start' => 400, 'end' => 410],
                    ['record_id' => '01m81q428bt5dn87eat575754z', 'start' => 3, 'end' => 9],
                ])),
        ]);
        $this->transcript($record, [[3.0, 9.0, 'The council approved a budget of 2 million pesos.']]);
        $question = $this->question('What did they say about the budget?', $record->creator, $record);
        Cache::add(AnswerChatQuestionJob::lockKey($question->user_id), true, 60);

        AnswerChatQuestionJob::dispatchSync($question);

        $reply = $this->reply();
        $this->assertSame(ChatMessageStatus::Answered, $reply->status);
        $this->assertSame([[
            'record_id' => $record->id,
            'title' => 'Budget hearing',
            'start' => 3,
            'end' => 9,
            'url' => route('records.show', ['record' => $record->id, 't' => 3]),
        ]], $reply->citations);
        $this->assertSame(['search_transcript'], $reply->tools);
        $this->assertFalse(Cache::has(AnswerChatQuestionJob::lockKey($question->user_id)));
        Event::assertDispatched(ChatAnswered::class, fn (ChatAnswered $event): bool => $event->userId === $question->user_id);

        // The page is named in the context; the first step must look something up.
        $first = $this->chatRequests()[0];
        $this->assertSame('required', $first['tool_choice']);
        $this->assertStringContainsString("record {$record->id}, titled \"Budget hearing\"", $first['context']);
        $output = json_decode($this->chatRequests()[1]['steps'][0]['results'][0]['output'], true);
        $this->assertSame('keyword', $output['method']);
    }

    public function test_drafts_and_records_the_account_may_not_see_are_never_read(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $viewer = User::factory()->create();
        $draft = Record::factory()->create(['title' => 'Unreleased draft']);
        $confidential = Record::factory()->approved()->create(['security_classification' => 'confidential']);
        Http::fake(['http://ai-api.test/chat' => Http::sequence()
            ->push($this->toolCalls([
                ['id' => 'c1', 'name' => 'get_record_overview', 'arguments' => ['record_id' => $draft->id]],
                ['id' => 'c2', 'name' => 'get_record_overview', 'arguments' => ['record_id' => $confidential->id]],
            ]))
            ->push($this->answer('It is about the budget.', true, [])),
        ]);
        // Even asked from the draft's page, the draft isn't context.
        $question = $this->question('What is this video about?', $viewer, $draft);

        AnswerChatQuestionJob::dispatchSync($question);

        $results = collect($this->chatRequests()[1]['steps'][0]['results'])->map(fn (array $result): array => json_decode($result['output'], true));
        $this->assertSame(['No approved record with that id is available to this account. Use search_records to find one.'], $results->pluck('error')->unique()->values()->all());
        $this->assertStringContainsString('not on a record', $this->chatRequests()[0]['context']);
        $this->assertSame(ChatMessageStatus::NotFound, $this->reply()->status);
        $this->assertStringNotContainsString('Unreleased draft', json_encode(Http::recorded()->map(fn (array $pair) => $pair[0]->data())->all()) ?: '');
    }

    public function test_a_public_viewer_gets_only_public_fields_and_a_watch_page_link(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $viewer = User::factory()->create();
        $record = Record::factory()->approved()->create(['accession_number' => 'ACC-2026-0001']);
        Http::fake(['http://ai-api.test/chat' => Http::sequence()
            ->push($this->toolCalls([
                ['id' => 'c1', 'name' => 'get_record_overview', 'arguments' => ['record_id' => $record->id]],
                ['id' => 'c2', 'name' => 'get_technical_details', 'arguments' => ['record_id' => $record->id]],
                ['id' => 'c3', 'name' => 'read_transcript', 'arguments' => ['record_id' => $record->id, 'start_seconds' => 0, 'end_seconds' => 60]],
            ]))
            ->push($this->answer('The mayor welcomes everyone.', true, [['record_id' => $record->id, 'start' => 1, 'end' => 4]])),
        ]);
        $this->transcript($record, [[1.0, 4.0, 'Welcome, everyone.']]);

        AnswerChatQuestionJob::dispatchSync($this->question('How does it start?', $viewer, $record));

        $results = collect($this->chatRequests()[1]['steps'][0]['results'])->mapWithKeys(fn (array $result): array => [$result['id'] => json_decode($result['output'], true)]);
        $this->assertSame('public', $results['c1']['access']);
        $this->assertArrayNotHasKey('accession_number', $results['c1']);
        $this->assertSame('Technical file details are not public for this record.', $results['c2']['reason']);
        $this->assertSame('Welcome, everyone.', $results['c3']['lines'][0]['text']);
        $this->assertSame(route('catalogue.watch', ['record' => $record->id, 't' => 1]), $this->reply()->citations[0]['url']);
    }

    public function test_search_records_finds_visible_records_by_catalogue_and_spoken_words(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $encoder = User::factory()->encoder()->create();
        $own = Record::factory()->approved()->for($encoder, 'creator')->create(['title' => 'Flood response briefing']);
        $public = Record::factory()->approved()->create(['title' => 'Town fiesta']);
        $hidden = Record::factory()->approved()->create(['title' => 'Flood drill (restricted)', 'security_classification' => 'restricted']);
        $this->transcript($public, [[30.0, 35.0, 'The flood waters reached the plaza.']]);
        Http::fake(['http://ai-api.test/chat' => Http::sequence()
            ->push($this->toolCalls([['id' => 'c1', 'name' => 'search_records', 'arguments' => ['query' => 'flood', 'limit' => null]]]))
            ->push($this->answer('Two videos mention floods.', true, [['record_id' => $public->id, 'start' => 30, 'end' => 35]])),
        ]);

        AnswerChatQuestionJob::dispatchSync($this->question('Which videos talk about floods?', $encoder));

        $found = json_decode($this->chatRequests()[1]['steps'][0]['results'][0]['output'], true);
        $this->assertSame([$own->id, $public->id], array_column($found['records'], 'record_id'));
        $this->assertSame(['full', 'public'], array_column($found['records'], 'access'));
        $this->assertSame('The flood waters reached the plaza.', $found['records'][1]['spoken_lines'][0]['text']);
        $this->assertNotContains($hidden->id, array_column($found['records'], 'record_id'));
        $this->assertSame($public->id, $this->reply()->citations[0]['record_id']);
    }

    public function test_bad_arguments_and_unknown_tools_get_an_error_result_instead_of_running(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $record = Record::factory()->approved()->create();
        Http::fake(['http://ai-api.test/chat' => Http::sequence()
            ->push($this->toolCalls([
                ['id' => 'c1', 'name' => 'read_transcript', 'arguments' => ['record_id' => $record->id, 'start_seconds' => 60, 'end_seconds' => 10]],
                ['id' => 'c2', 'name' => 'drop_tables', 'arguments' => []],
            ]))
            ->push($this->answer('I could not read that part.', false, [])),
        ]);

        AnswerChatQuestionJob::dispatchSync($this->question('What happens at one minute?', $record->creator));

        $results = collect($this->chatRequests()[1]['steps'][0]['results'])->mapWithKeys(fn (array $result): array => [$result['id'] => json_decode($result['output'], true)]);
        $this->assertStringContainsString('end seconds field must be greater than', $results['c1']['error']);
        $this->assertSame('There is no tool named drop_tables.', $results['c2']['error']);
        $this->assertSame(ChatMessageStatus::NotFound, $this->reply()->status);
    }

    public function test_the_last_step_must_answer_and_usage_is_billed_to_the_asker(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $admin = User::factory()->admin()->create();
        $search = $this->toolCalls([['id' => 'c', 'name' => 'search_records', 'arguments' => ['query' => null, 'limit' => null]]]);
        Record::factory()->approved()->create();
        Http::fake(['http://ai-api.test/chat' => Http::sequence()->push($search)->push($search)->push($search)->push($search)
            ->push($this->answer('There is one video.', true, []))]);

        AnswerChatQuestionJob::dispatchSync($this->question('What videos are there?', $admin));

        $last = $this->chatRequests()[4];
        $this->assertSame('none', $last['tool_choice']);
        $this->assertCount(4, $last['steps']);
        $this->assertSame(ChatMessageStatus::Answered, $this->reply()->status);
        $this->assertSame([$admin->id], UsageRecord::query()->distinct()->pluck('user_id')->all());
    }

    public function test_a_provider_error_becomes_a_failed_reply_left_out_of_later_history(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/chat' => Http::sequence()
            ->push(['detail' => 'OpenAI rejected the API key.'], 502)
            ->push($this->toolCalls([['id' => 'c1', 'name' => 'search_records', 'arguments' => ['query' => null, 'limit' => null]]]))
            ->push($this->answer('Nothing yet.', false, [])),
        ]);
        $user = User::factory()->create();

        AnswerChatQuestionJob::dispatchSync($this->question('Who spoke?', $user));

        $this->assertSame('OpenAI rejected the API key.', $this->reply()->content);
        $this->assertSame(ChatMessageStatus::Failed, $this->reply()->status);

        AnswerChatQuestionJob::dispatchSync($this->question('What videos are there?', $user));

        $this->assertSame([], $this->chatRequests()[1]['history']);
    }

    public function test_a_deactivated_account_gets_no_answer(): void
    {
        Event::fake([ChatAnswered::class]);
        Http::preventStrayRequests();
        $question = $this->question('Who spoke?', User::factory()->inactive()->create());

        AnswerChatQuestionJob::dispatchSync($question);

        Http::assertNothingSent();
        $this->assertSame(ChatMessageStatus::Failed, $this->reply()->status);
    }

    private function question(string $text, User $user, ?Record $page = null): ChatMessage
    {
        $question = new ChatMessage(['role' => ChatRole::User, 'content' => $text]);
        $question->record_id = $page?->id;
        $question->user_id = $user->id;
        $question->status = ChatMessageStatus::Pending;
        $question->save();

        return $question;
    }

    private function reply(): ChatMessage
    {
        return ChatMessage::query()->where('role', ChatRole::Assistant)->latest('id')->firstOrFail();
    }

    /**
     * @return list<Request>
     */
    private function chatRequests(): array
    {
        return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/chat'))
            ->map(fn (array $pair): Request => $pair[0])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{0: float, 1: float, 2: string}>  $lines
     */
    private function transcript(Record $record, array $lines): void
    {
        $transcript = RecordTranscript::factory()->for($record)->create();

        foreach ($lines as $position => [$start, $end, $text]) {
            RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => $position, 'start' => $start, 'end' => $end, 'text' => $text, 'original_text' => $text]);
        }
    }

    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls
     * @return array<string, mixed>
     */
    private function toolCalls(array $calls): array
    {
        return ['type' => 'tool_calls', 'tool_calls' => $calls, 'answer' => null, 'model' => 'gpt-6.2-sol', 'usage' => [
            ['kind' => 'llm_input_tokens', 'quantity' => 800, 'estimated_cost' => 0.0016],
            ['kind' => 'llm_output_tokens', 'quantity' => 0, 'estimated_cost' => 0.0],
        ]];
    }

    /**
     * @param  list<array{record_id: string, start: float, end: float}>  $citations
     * @return array<string, mixed>
     */
    private function answer(string $text, bool $grounded, array $citations): array
    {
        return ['type' => 'answer', 'tool_calls' => [], 'answer' => ['answer' => $text, 'grounded' => $grounded, 'citations' => $citations], 'model' => 'gpt-6.2-sol', 'usage' => [
            ['kind' => 'llm_input_tokens', 'quantity' => 1200, 'estimated_cost' => 0.0024],
            ['kind' => 'llm_output_tokens', 'quantity' => 40, 'estimated_cost' => 0.0004],
        ]];
    }
}
