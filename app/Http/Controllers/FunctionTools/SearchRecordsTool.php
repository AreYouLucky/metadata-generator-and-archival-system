<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Models\RecordSegment;
use App\Services\Chat\ChatScope;
use App\Services\Chat\TranscriptSearch;
use App\Services\Records\VisualsPresenter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds approved records the asker may see: by title, description, event
 * (project and series), place, confirmed keywords and subjects, and by
 * words spoken in their transcripts. Without a query it lists the newest.
 * The other tools take the `record_id`s it returns.
 */
class SearchRecordsTool implements FunctionTool
{
    public const int MAX_RESULTS = 10;

    private const int CANDIDATES = 60;

    private const int LINES_PER_RECORD = 2;

    public function name(): string
    {
        return 'search_records';
    }

    public function description(): string
    {
        return 'Find videos in the archive this person may see (approved records only). Matches titles, '
            .'descriptions, events (projects and series), places, keywords and subjects, and words SPOKEN in the '
            .'videos (returns those lines with times). Use short keywords or names, not a whole question. '
            .'With query null, lists the newest records. Returns record ids for the other tools.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => ['string', 'null'], 'description' => 'Keywords, names or a phrase; null for the newest records.'],
                'limit' => ['type' => ['integer', 'null'], 'description' => 'How many records (1-10, default 6).'],
            ],
            'required' => ['query', 'limit'],
            'additionalProperties' => false,
        ];
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:300'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_RESULTS],
        ];
    }

    public function handle(ChatScope $scope, array $arguments): ToolResult
    {
        $limit = (int) ($arguments['limit'] ?? 6);
        $terms = TranscriptSearch::terms((string) ($arguments['query'] ?? ''));
        $total = $scope->records()->count();

        if ($total === 0) {
            return ToolResult::unavailable('There are no approved records this account may see yet.');
        }

        if ($terms === []) {
            $records = $this->columns($scope->records())->latest('records.published_at')->limit($limit)->get();

            return ToolResult::found([
                'total_records_available' => $total,
                'records' => $records->map(fn (Record $record): array => $this->present($record, $scope, []))->all(),
            ]);
        }

        $lines = $this->spokenLines($scope, $terms);
        $catalogue = $this->catalogueMatches($scope, $terms);
        $ids = array_values(array_unique([...$catalogue, ...array_keys($lines)]));

        if ($ids === []) {
            return ToolResult::unavailable('No record this account may see matches these words.', ['total_records_available' => $total]);
        }

        $records = $this->columns($scope->records())->whereIn('records.id', $ids)->get()->keyBy('id');
        $scored = [];

        foreach ($records as $id => $record) {
            $text = mb_strtolower(implode(' ', [$record->title, $record->alternative_title, $record->description, $record->project?->title, $record->projectSeries?->title]));
            $inCatalogue = count(array_filter($terms, fn (string $term): bool => str_contains($text, $term)));
            $spoken = $lines[$id] ?? [];
            $inSpeech = count(array_filter($terms, fn (string $term): bool => array_any($spoken, fn (array $line): bool => mb_stripos($line['text'], $term) !== false)));
            $scored[] = ['record' => $record, 'lines' => $spoken, 'score' => 2 * $inCatalogue + $inSpeech + (in_array($id, $catalogue, true) ? 1 : 0)];
        }

        usort($scored, fn (array $first, array $second): int => $second['score'] <=> $first['score']);
        $scored = array_slice($scored, 0, $limit);
        $moments = [];

        foreach ($scored as $match) {
            foreach (array_slice($match['lines'], 0, self::LINES_PER_RECORD) as $line) {
                $moments[] = ['record_id' => $match['record']->id, 'start' => $line['start'], 'end' => $line['end']];
            }
        }

        return ToolResult::found([
            'total_records_available' => $total,
            'records' => array_map(fn (array $match): array => $this->present($match['record'], $scope, array_slice($match['lines'], 0, self::LINES_PER_RECORD)), $scored),
        ], $moments);
    }

    /**
     * IDs of records whose catalogue fields contain any of the words.
     *
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function catalogueMatches(ChatScope $scope, array $terms): array
    {
        return array_values($scope->records()
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.addcslashes($term, '%_\\').'%';
                    $query->orWhereLike('records.title', $like)
                        ->orWhereLike('records.alternative_title', $like)
                        ->orWhereLike('records.description', $like)
                        ->orWhereLike('records.location', $like)
                        ->orWhereLike('records.accession_number', $like)
                        ->orWhereHas('project', fn (Builder $project) => $project->whereLike('title', $like))
                        ->orWhereHas('projectSeries', fn (Builder $series) => $series->whereLike('title', $like))
                        ->orWhereHas('keywords', fn (Builder $keyword) => $keyword->where('is_confirmed', true)->whereLike('keyword', $like))
                        ->orWhereHas('subjects', fn (Builder $subject) => $subject->where('is_confirmed', true)->whereLike('subject', $like));
                }
            })
            ->limit(self::CANDIDATES)
            ->get(['records.id'])
            ->map(fn (Record $record): string => $record->id)
            ->all());
    }

    /**
     * Transcript lines (original language) containing any of the words, by record.
     *
     * @param  list<string>  $terms
     * @return array<string, list<array{start: float, end: float, text: string}>>
     */
    private function spokenLines(ChatScope $scope, array $terms): array
    {
        $rows = RecordSegment::query()
            ->join('record_transcripts', 'record_transcripts.id', '=', 'record_segments.record_transcript_id')
            ->where('record_transcripts.is_original', true)
            ->whereIn('record_transcripts.record_id', $scope->records()->select('records.id'))
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhereLike('record_segments.text', '%'.addcslashes($term, '%_\\').'%');
                }
            })
            ->orderBy('record_segments.start')
            ->limit(self::CANDIDATES * 3)
            ->get(['record_transcripts.record_id', 'record_segments.start', 'record_segments.end', 'record_segments.text']);

        $lines = [];

        foreach ($rows as $row) {
            $lines[(string) $row->getAttribute('record_id')][] = [
                'start' => (float) $row->start,
                'end' => (float) $row->end,
                'text' => mb_strimwidth($row->text, 0, 300, '…'),
            ];
        }

        return $lines;
    }

    /**
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    private function columns(Builder $query): Builder
    {
        return $query
            ->select(['records.id', 'records.created_by', 'records.title', 'records.alternative_title', 'records.description', 'records.project_id', 'records.project_series_id', 'records.date_recorded', 'records.published_at', 'records.language'])
            ->with(['project:id,title', 'projectSeries:id,title']);
    }

    /**
     * @param  list<array{start: float, end: float, text: string}>  $lines
     * @return array<string, mixed>
     */
    private function present(Record $record, ChatScope $scope, array $lines): array
    {
        return array_filter([
            'record_id' => $record->id,
            'title' => $record->title,
            'event' => $record->project?->title,
            'series' => $record->projectSeries?->title,
            'date' => ($record->date_recorded ?? $record->published_at)?->toDateString(),
            'language' => $record->language,
            'summary' => $record->description === null ? null : mb_strimwidth($record->description, 0, 300, '…'),
            'spoken_lines' => array_map(fn (array $line): array => [...$line, 'time' => VisualsPresenter::clock($line['start'])], $lines),
            'access' => $scope->seesInternal($record) ? 'full' : 'public',
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
