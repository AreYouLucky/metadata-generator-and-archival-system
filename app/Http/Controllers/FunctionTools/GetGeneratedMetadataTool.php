<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Services\Chat\ChatScope;
use App\Services\Records\VisualsPresenter;

/**
 * The active AI generation: summary, titles, description, tags, keywords,
 * hashtags, chapters, key quotes, tone and content flags. Chapters and
 * quotes carry times, so answers built on them can be cited. Public viewers
 * get only what the watch page shows: the description and the chapters.
 */
class GetGeneratedMetadataTool extends RecordTool
{
    private const int MAX_ITEMS = 30;

    public function name(): string
    {
        return 'get_generated_metadata';
    }

    public function description(): string
    {
        return 'The generated description and chapters (with start times) of one record; with full access also '
            .'its summary, title options, tags, keywords, hashtags, key quotes (with times and speakers), tone '
            .'and content flags. Good for "what is this video about", the outline, or the key quotes.';
    }

    protected function properties(): array
    {
        return [];
    }

    protected function recordRules(): array
    {
        return [];
    }

    protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult
    {
        $metadata = $record->activeMetadata()->first(['id', 'record_id', 'preset', 'payload', 'created_at']);

        if ($metadata === null) {
            return ToolResult::unavailable('No AI metadata has been generated for this record yet.');
        }

        $payload = $metadata->payload;
        $chapters = array_slice($this->list($payload['chapters'] ?? []), 0, self::MAX_ITEMS);
        $quotes = array_slice($this->list($payload['quotes'] ?? []), 0, self::MAX_ITEMS);
        $moments = [];

        if (! $internal) {
            $quotes = [];
        }

        foreach ($chapters as $index => $chapter) {
            $start = (float) ($chapter['start'] ?? 0);
            $next = $chapters[$index + 1]['start'] ?? null;
            $moments[] = ['start' => $start, 'end' => is_numeric($next) ? (float) $next : $start + 60.0];
        }

        foreach ($quotes as $quote) {
            $start = (float) ($quote['start'] ?? 0);
            $moments[] = ['start' => $start, 'end' => (float) ($quote['end'] ?? $start + 15.0)];
        }

        $chapterList = array_map(fn (array $chapter): array => [
            'start' => (float) ($chapter['start'] ?? 0),
            'time' => VisualsPresenter::clock((float) ($chapter['start'] ?? 0)),
            'title' => $chapter['title'] ?? null,
        ], array_filter($chapters, 'is_array'));

        if (! $internal) {
            return ToolResult::found([
                'description' => $record->description ?? ($payload['description'] ?? null),
                'chapters' => $chapterList,
            ], $moments);
        }

        return ToolResult::found([
            'preset' => $metadata->preset->value,
            'generated_at' => $metadata->created_at?->toIso8601String(),
            'summary' => $payload['summary'] ?? null,
            'selected_title' => $payload['selectedTitle'] ?? null,
            'title_options' => array_slice($this->list($payload['titles'] ?? []), 0, 10),
            'description' => $payload['description'] ?? null,
            'tags' => array_slice($this->list($payload['tags'] ?? []), 0, self::MAX_ITEMS),
            'keywords' => array_slice($this->list($payload['keywords'] ?? []), 0, self::MAX_ITEMS),
            'hashtags' => array_slice($this->list($payload['hashtags'] ?? []), 0, self::MAX_ITEMS),
            'chapters' => $chapterList,
            'quotes' => array_map(fn (array $quote): array => [
                'start' => (float) ($quote['start'] ?? 0),
                'end' => isset($quote['end']) ? (float) $quote['end'] : null,
                'time' => VisualsPresenter::clock((float) ($quote['start'] ?? 0)),
                'speaker' => $quote['speaker'] ?? null,
                'text' => $quote['text'] ?? null,
            ], array_filter($quotes, 'is_array')),
            'tone' => $payload['tone'] ?? null,
            'content_flags' => $payload['contentFlags'] ?? null,
        ], $moments);
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
