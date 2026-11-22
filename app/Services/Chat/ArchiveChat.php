<?php

namespace App\Services\Chat;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatRole;
use App\Enums\UsageKind;
use App\Http\Controllers\FunctionTools\FunctionToolbox;
use App\Http\Controllers\FunctionTools\ToolResult;
use App\Models\ChatMessage;
use App\Models\Record;
use App\Models\UsageRecord;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\VisualsPresenter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Answers one "Ask me" question (Phase 7) with a function-calling loop: the
 * AI service's model asks for tool calls, this class runs them through the
 * FunctionToolbox within the asker's ChatScope (approved records the account
 * may see, public fields unless it may open the record), and sends the
 * results back until the model answers.
 *
 * Grounding is enforced here, not only in the prompt:
 * - the first step must call a tool (`tool_choice: required`);
 * - an answer counts as backed by the archive only if the model says so AND
 *   at least one tool actually returned data; otherwise it is `not_found`;
 * - citations are kept only where they fall on a moment a tool returned for
 *   that same record, and link to where this account may watch it.
 */
class ArchiveChat
{
    /** Model calls per question; the last one must answer. */
    public const int MAX_STEPS = 5;

    /** Tool calls run per step (the rest get an error result). */
    public const int MAX_CALLS_PER_STEP = 4;

    /** The AI service accepts at most this many calls in one step. */
    private const int MAX_CALLS_SENT = 8;

    public const int HISTORY_MESSAGES = 8;

    private const int HISTORY_CHARS = 1500;

    public const int MAX_CITATIONS = 6;

    /** Seconds a citation may sit outside a returned moment. */
    private const float CITATION_TOLERANCE = 2.0;

    public function __construct(
        private AiServiceClient $client,
        private FunctionToolbox $toolbox,
        private ChatUsageMeter $meter,
    ) {}

    /**
     * Answer the question and store the reply (marking the question
     * answered). Never throws for AI-service problems: they become a
     * `failed` reply with an explanation.
     */
    public function answer(ChatMessage $question): ChatMessage
    {
        $scope = new ChatScope($question->user);
        // The page the question was asked on, only if this account may ask about it.
        $page = $scope->find($question->record_id);
        $steps = [];
        $moments = [];
        $found = false;
        $used = [];
        $history = $this->history($question);

        try {
            for ($step = 0; $step < self::MAX_STEPS; $step++) {
                $response = $this->client->chat([
                    'record_id' => $page?->id,
                    'question' => $question->content,
                    'context' => $this->context($page, $scope),
                    'history' => $history,
                    'tools' => $this->toolbox->definitions(),
                    'steps' => $steps,
                    'tool_choice' => match (true) {
                        $step === 0 => 'required',
                        $step === self::MAX_STEPS - 1 => 'none',
                        default => 'auto',
                    },
                ]);
                $this->meter->add($response['usage'] ?? []);

                if (($response['type'] ?? null) !== 'tool_calls') {
                    $answer = $response['answer'] ?? null;

                    return is_array($answer)
                        ? $this->reply($question, $scope, $answer, $found, $moments, $used, $page)
                        : $this->fail($question, 'The assistant returned no answer. Try again.');
                }

                $calls = [];
                $results = [];

                // The AI service validates this shape (ChatResponse), so calls are well-formed.
                foreach (array_slice($response['tool_calls'] ?? [], 0, self::MAX_CALLS_SENT) as $index => $call) {
                    $name = $call['name'];
                    $arguments = $call['arguments'];
                    $result = $index < self::MAX_CALLS_PER_STEP
                        ? $this->toolbox->run($scope, $name, $arguments)
                        : ToolResult::error('Too many tool calls at once. Call at most '.self::MAX_CALLS_PER_STEP.' per step.');

                    if ($result->available) {
                        $found = true;
                        array_push($moments, ...$result->moments);
                    }

                    if ($this->toolbox->has($name) && ! in_array($name, $used, true)) {
                        $used[] = $name;
                    }

                    $calls[] = ['id' => (string) $call['id'], 'name' => $name, 'arguments' => (object) $arguments];
                    $results[] = ['id' => (string) $call['id'], 'output' => $result->toJson()];
                }

                if ($calls === []) {
                    return $this->fail($question, 'The assistant returned no answer. Try again.');
                }

                $steps[] = ['calls' => $calls, 'results' => $results];
            }

            return $this->fail($question, 'The assistant could not finish its answer. Try again.');
        } catch (RequestException $exception) {
            $detail = $exception->response->json('detail');
            Log::warning('Chat answer failed', ['user_id' => $question->user_id, 'status' => $exception->response->status()]);

            return $this->fail($question, is_string($detail) ? $detail : 'The AI service could not answer. Try again.');
        } catch (ConnectionException) {
            return $this->fail($question, 'The AI service could not be reached. Try again in a moment.');
        } finally {
            $this->bill($question);
        }
    }

    /**
     * Store the reply that failed, so the conversation shows why.
     */
    public function fail(ChatMessage $question, string $reason): ChatMessage
    {
        return $this->store($question, mb_substr($reason, 0, 500), ChatMessageStatus::Failed, [], []);
    }

    /**
     * What the model is told about where the person is (data, not instructions).
     */
    private function context(?Record $page, ChatScope $scope): string
    {
        $role = $scope->user->role->value;

        if ($page === null) {
            return "The person (role: {$role}) is not on a record's page. Find records with search_records.";
        }

        return "The person (role: {$role}) is on the page of record {$page->id}, titled \"{$page->title}\". "
            .'"This video" or "this record" means that record.';
    }

    /**
     * @param  array<string, mixed>  $answer
     * @param  list<array{record_id?: string, start: float, end: float}>  $moments
     * @param  list<string>  $used
     */
    private function reply(ChatMessage $question, ChatScope $scope, array $answer, bool $found, array $moments, array $used, ?Record $page): ChatMessage
    {
        $text = trim((string) ($answer['answer'] ?? ''));

        if ($text === '') {
            return $this->fail($question, 'The assistant returned an empty answer. Try again.');
        }

        // Backed by data only if the model says so and a tool really returned some.
        $grounded = ($answer['grounded'] ?? false) === true && $found;
        $citations = $grounded
            ? $this->citations(is_array($answer['citations'] ?? null) ? $answer['citations'] : [], $moments, $scope, $page)
            : [];

        return $this->store(
            $question,
            mb_substr($text, 0, 4000),
            $grounded ? ChatMessageStatus::Answered : ChatMessageStatus::NotFound,
            $citations,
            $used,
        );
    }

    /**
     * Keep citations that fall on a moment the tools returned for the same
     * record, distinct, at most MAX_CITATIONS, each with the record's title
     * and where this account may watch it.
     *
     * @param  array<mixed>  $cited
     * @param  list<array{record_id?: string, start: float, end: float}>  $moments
     * @return list<array{record_id: string, title: string, start: float, end: float, url: string}>
     */
    private function citations(array $cited, array $moments, ChatScope $scope, ?Record $page): array
    {
        $kept = [];

        foreach ($cited as $citation) {
            if (! is_array($citation) || ! is_numeric($citation['start'] ?? null) || ! is_numeric($citation['end'] ?? null)) {
                continue;
            }

            $recordId = strtolower(is_string($citation['record_id'] ?? null) && $citation['record_id'] !== '' ? $citation['record_id'] : (string) $page?->id);
            $start = round(min((float) $citation['start'], (float) $citation['end']), 2);
            $end = round(max((float) $citation['start'], (float) $citation['end']), 2);
            $key = "{$recordId}:{$start}-{$end}";
            $backed = array_any($moments, fn (array $moment): bool => ($moment['record_id'] ?? null) === $recordId
                && $start <= $moment['end'] + self::CITATION_TOLERANCE
                && $end >= $moment['start'] - self::CITATION_TOLERANCE);

            if ($start < 0 || ! $backed || isset($kept[$key])) {
                continue;
            }

            $kept[$key] = ['record_id' => $recordId, 'start' => $start, 'end' => $end];

            if (count($kept) === self::MAX_CITATIONS) {
                break;
            }
        }

        if ($kept === []) {
            return [];
        }

        // One query for the cited records, through the scope again.
        $records = $scope->records()
            ->whereIn('records.id', array_unique(array_column($kept, 'record_id')))
            ->get(['records.id', 'records.title', 'records.created_by'])
            ->keyBy('id');
        $result = [];

        foreach ($kept as $citation) {
            $record = $records->get($citation['record_id']);

            if ($record instanceof Record) {
                $result[] = [
                    ...$citation,
                    'title' => $record->title,
                    'url' => $scope->url($record, $citation['start']),
                    'time' => VisualsPresenter::clock($citation['start']),
                ];
            }
        }

        return array_map(fn (array $citation): array => [
            'record_id' => $citation['record_id'],
            'title' => $citation['title'],
            'start' => $citation['start'],
            'end' => $citation['end'],
            'url' => $citation['url'],
        ], $result);
    }

    /**
     * Earlier turns of this person's conversation, so follow-up questions
     * make sense. Failed replies (and their questions) are left out.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(ChatMessage $question): array
    {
        $messages = ChatMessage::query()
            ->where('user_id', $question->user_id)
            ->where('id', '<', $question->id)
            ->latest('id')
            ->limit(self::HISTORY_MESSAGES)
            ->get(['id', 'role', 'content', 'status'])
            ->reverse()
            ->values();

        $history = [];

        foreach ($messages as $index => $message) {
            $next = $messages->get($index + 1);
            $failedNext = $next !== null && $next->status === ChatMessageStatus::Failed;

            if ($message->status === ChatMessageStatus::Failed || $message->status === ChatMessageStatus::Pending
                || ($message->role === ChatRole::User && $failedNext)) {
                continue;
            }

            $history[] = ['role' => $message->role->value, 'content' => mb_substr($message->content, 0, self::HISTORY_CHARS)];
        }

        return $history;
    }

    /**
     * @param  list<array{record_id: string, title: string, start: float, end: float, url: string}>  $citations
     * @param  list<string>  $tools
     */
    private function store(ChatMessage $question, string $content, ChatMessageStatus $status, array $citations, array $tools): ChatMessage
    {
        return DB::transaction(function () use ($question, $content, $status, $citations, $tools): ChatMessage {
            $question->status = ChatMessageStatus::Answered;
            $question->save();

            $reply = new ChatMessage([
                'role' => ChatRole::Assistant,
                'content' => $content,
                'citations' => $citations === [] ? null : $citations,
                'tools' => $tools === [] ? null : $tools,
            ]);
            $reply->record_id = $question->record_id;
            $reply->user_id = $question->user_id;
            $reply->status = $status;
            $reply->save();

            return $reply;
        });
    }

    /**
     * Usage of this answer (model steps and the tools' embeddings), billed
     * to the person who asked.
     */
    private function bill(ChatMessage $question): void
    {
        foreach ($this->meter->take() as $item) {
            $kind = UsageKind::tryFrom($item['kind']);

            if ($kind === null || (float) $item['quantity'] <= 0) {
                continue;
            }

            UsageRecord::create([
                'user_id' => $question->user_id,
                'record_id' => $question->record_id,
                'kind' => $kind,
                'quantity' => $item['quantity'],
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'occurred_at' => now(),
            ]);
        }
    }
}
