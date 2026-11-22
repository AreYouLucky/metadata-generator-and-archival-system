<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Services\Chat\ChatScope;
use App\Services\Chat\TranscriptSearch;

/**
 * "What did they say about X?": the transcript passages most related to a
 * topic, by meaning (embeddings) with a bonus for exact words.
 */
class SearchTranscriptTool extends RecordTool
{
    public function __construct(private TranscriptSearch $search) {}

    public function name(): string
    {
        return 'search_transcript';
    }

    public function description(): string
    {
        return 'Search what was SAID in the video. Returns the most relevant transcript passages (about 45 s each, '
            .'with start/end seconds and speaker names when known), best first. Use a short topic, name or '
            .'phrase as the query, not a whole question. Works across English and Filipino.';
    }

    protected function properties(): array
    {
        return [
            'query' => ['type' => 'string', 'description' => 'Topic, name or phrase to look for.'],
            'limit' => ['type' => ['integer', 'null'], 'description' => 'How many passages (1-8, default 5).'],
        ];
    }

    protected function recordRules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:300'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.TranscriptSearch::MAX_RESULTS],
        ];
    }

    protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult
    {
        $result = $this->search->search($record, (string) $arguments['query'], (int) ($arguments['limit'] ?? 5));

        if ($result === null) {
            return ToolResult::unavailable('This record has no transcript yet (it may still be processing, or nothing was said).');
        }

        if ($result['passages'] === []) {
            return ToolResult::unavailable('No part of the transcript is about this.', ['method' => $result['method']]);
        }

        return ToolResult::found(
            $result,
            array_map(fn (array $passage): array => ['start' => $passage['start'], 'end' => $passage['end']], $result['passages']),
        );
    }
}
