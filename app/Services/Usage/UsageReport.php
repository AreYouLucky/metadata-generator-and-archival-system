<?php

namespace App\Services\Usage;

use App\Enums\UsageKind;
use App\Models\Record;
use App\Models\UsageRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Metered AI usage (`usage_records`) for a date range, summed in the
 * database: totals, per encoder, per record and per day. Behind the admin
 * Usage page, its CSV download and the overview's "this month".
 */
class UsageReport
{
    /**
     * The summed columns: one per usage kind, plus the estimated cost.
     *
     * @var array<string, UsageKind>
     */
    private const array COLUMNS = [
        'minutes' => UsageKind::TranscriptionMinutes,
        'input_tokens' => UsageKind::LlmInputTokens,
        'output_tokens' => UsageKind::LlmOutputTokens,
        'vision_frames' => UsageKind::VisionFrames,
    ];

    public function __construct(
        private CarbonImmutable $from,
        private CarbonImmutable $to,
    ) {}

    /**
     * Whole days, inclusive.
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->startOfDay(), $to->endOfDay());
    }

    /**
     * @return array{minutes: float, input_tokens: float, output_tokens: float, vision_frames: float, cost: float}
     */
    public function totals(): array
    {
        $row = $this->summed($this->query())->first();

        return $this->numbers($row);
    }

    /**
     * Per encoder, highest cost first. Every encoder with usage in the range.
     *
     * @return list<array{user_id: int|null, name: string, minutes: float, input_tokens: float, output_tokens: float, vision_frames: float, cost: float}>
     */
    public function byEncoder(): array
    {
        $rows = $this->summed($this->query())
            ->addSelect('user_id')
            ->groupBy('user_id')
            ->orderByDesc('cost')
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('user_id')->filter())->pluck('name', 'id');

        return array_values($rows->map(fn (UsageRecord $row): array => [
            'user_id' => $row->user_id,
            'name' => $row->user_id === null ? 'Deleted account' : (string) ($names[$row->user_id] ?? 'Deleted account'),
            ...$this->numbers($row),
        ])->all());
    }

    /**
     * Per record, highest cost first, optionally for one encoder.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function byRecord(?int $userId, int $perPage): LengthAwarePaginator
    {
        $page = $this->recordQuery($userId)->paginate($perPage);

        $records = $this->recordTitles(collect($page->items())->pluck('record_id'));

        return $page->through(fn (UsageRecord $row): array => $this->recordRow($row, $records));
    }

    /**
     * Every record row, for the CSV, read in chunks.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function recordRows(?int $userId): LazyCollection
    {
        return $this->recordQuery($userId)
            ->lazy(500)
            ->chunk(500)
            ->flatMap(function (LazyCollection $chunk): array {
                $records = $this->recordTitles($chunk->pluck('record_id')->collect());

                return $chunk->map(fn (UsageRecord $row): array => $this->recordRow($row, $records))->values()->all();
            });
    }

    /**
     * Minutes and cost per day, every day of the range (zeros included), for
     * the chart.
     *
     * @return list<array{date: string, minutes: float, cost: float}>
     */
    public function daily(): array
    {
        $rows = $this->summed($this->query())
            ->selectRaw('DATE(occurred_at) as day')
            ->groupByRaw('DATE(occurred_at)')
            ->get()
            ->keyBy(fn (UsageRecord $row): string => substr((string) $row->getAttribute('day'), 0, 10));

        $days = [];

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $numbers = $this->numbers($row);
            $days[] = ['date' => $day->toDateString(), 'minutes' => $numbers['minutes'], 'cost' => $numbers['cost']];
        }

        return $days;
    }

    /**
     * @return Builder<UsageRecord>
     */
    private function query(): Builder
    {
        return UsageRecord::query()->whereBetween('occurred_at', [$this->from, $this->to]);
    }

    /**
     * @return Builder<UsageRecord>
     */
    private function recordQuery(?int $userId): Builder
    {
        return $this->summed($this->query())
            ->addSelect(['record_id', 'user_id'])
            ->whereNotNull('record_id')
            ->when($userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->groupBy(['record_id', 'user_id'])
            ->orderByDesc('cost')
            ->orderBy('record_id');
    }

    /**
     * Conditional sums with bound kinds, never interpolated.
     *
     * @param  Builder<UsageRecord>  $query
     * @return Builder<UsageRecord>
     */
    private function summed(Builder $query): Builder
    {
        foreach (self::COLUMNS as $alias => $kind) {
            $query->selectRaw("COALESCE(SUM(CASE WHEN kind = ? THEN quantity ELSE 0 END), 0) as {$alias}", [$kind->value]);
        }

        return $query->selectRaw('COALESCE(SUM(estimated_cost), 0) as cost');
    }

    /**
     * @return array{minutes: float, input_tokens: float, output_tokens: float, vision_frames: float, cost: float}
     */
    private function numbers(?UsageRecord $row): array
    {
        $value = fn (string $column, int $precision): float => round((float) ($row?->getAttribute($column) ?? 0), $precision);

        return [
            'minutes' => $value('minutes', 2),
            'input_tokens' => $value('input_tokens', 0),
            'output_tokens' => $value('output_tokens', 0),
            'vision_frames' => $value('vision_frames', 0),
            'cost' => $value('cost', 4),
        ];
    }

    /**
     * Titles of the records on one page (deleted ones too: usage outlives them).
     *
     * @param  Collection<int, mixed>  $ids
     * @return Collection<string, Record>
     */
    private function recordTitles(Collection $ids): Collection
    {
        return Record::withTrashed()
            ->whereIn('id', $ids->filter()->unique()->values())
            ->with('creator:id,name')
            ->get(['id', 'title', 'accession_number', 'created_by', 'deleted_at'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<string, Record>  $records
     * @return array<string, mixed>
     */
    private function recordRow(UsageRecord $row, Collection $records): array
    {
        $record = $records->get((string) $row->record_id);

        return [
            'record_id' => $row->record_id,
            'title' => $record?->title,
            'accession_number' => $record?->accession_number,
            'encoder' => $record?->creator?->name,
            'deleted' => $record === null || $record->trashed(),
            ...$this->numbers($row),
        ];
    }
}
