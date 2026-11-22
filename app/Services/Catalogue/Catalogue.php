<?php

namespace App\Services\Catalogue;

use App\Enums\TaskStatus;
use App\Exporters\TranscriptLines;
use App\Models\Genre;
use App\Models\ProductionAgency;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\RecordContributor;
use App\Models\RecordFrame;
use App\Models\RecordKeyword;
use App\Models\RecordSubject;
use App\Models\Region;
use App\Models\VideoType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * The public catalogue (PROJECT_SPEC §1, §8): only records that pass the
 * distribution rule, and only the public fields (title, alternative title,
 * description, date recorded, project, series, genre, video type, agency,
 * place names, confirmed subjects and keywords, credits, chapters,
 * transcript, licence and usage restrictions). Never rights notes, release
 * forms, provenance, checksums, unconfirmed suggestions, classification or
 * usage. Results are cached (CatalogueCache) and the cache is cleared when a
 * record is approved, reopened or edited while public.
 *
 * A record's catalogue date is its date recorded, falling back to the
 * project's first day, then the day it was published.
 */
class Catalogue
{
    public const int PER_PAGE = 24;

    private const int HOME_LATEST = 8;

    private const int HOME_PROJECTS = 6;

    private const int RELATED = 6;

    private const int PROJECT_OPTIONS = 200;

    public function __construct(private CatalogueCache $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        return $this->cache->remember('home', fn (): array => [
            'latest' => $this->cards($this->records()->reorder()->orderByDesc('records.published_at')->orderByDesc('records.id')->limit(self::HOME_LATEST)->get()),
            'projects' => $this->projectCards(self::HOME_PROJECTS),
            'genres' => $this->facets()['genres'],
            'total' => $this->publicBase()->count('records.id'),
        ]);
    }

    /**
     * @param  array{project?: string|null, series?: string|null, genre?: string|null, type?: string|null, agency?: int|null, region?: int|null, year?: int|null, month?: int|null, q?: string|null, sort?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function browse(array $filters, int $page): array
    {
        ksort($filters);
        $key = 'browse:'.md5((string) json_encode([$filters, $page]));

        return $this->cache->remember($key, function () use ($filters, $page): array {
            $query = $this->records();
            $this->applyFilters($query, $filters);
            $this->applySort($query, $filters['sort'] ?? null);

            $paginator = $query->paginate(self::PER_PAGE, page: $page)->withQueryString();
            $cards = $this->cards($paginator->items());

            return [...$paginator->toArray(), 'data' => $cards];
        });
    }

    /**
     * Filter options with how many public records each has. Only options in
     * use appear, so an empty filter is never offered.
     *
     * @return array{genres: list<array{slug: string, name: string, count: int}>, types: list<array{slug: string, name: string, count: int}>, agencies: list<array{id: int, name: string, acronym: string|null, count: int}>, regions: list<array{id: int, name: string, description: string|null, count: int}>, projects: list<array{id: string, title: string, count: int}>, years: list<array{year: int, count: int}>}
     */
    public function facets(): array
    {
        return $this->cache->remember('facets', function (): array {
            $counts = fn (string $column) => $this->publicBase()
                ->select("records.{$column}")
                ->selectRaw('COUNT(*) as aggregate')
                ->whereNotNull("records.{$column}")
                ->groupBy("records.{$column}")
                ->toBase()
                ->pluck('aggregate', $column);

            $genreCounts = $counts('genre_id');
            $typeCounts = $counts('video_type_id');
            $agencyCounts = $counts('agency_id');
            $regionCounts = $counts('region_id');
            $projectCounts = $counts('project_id');

            return [
                'genres' => array_values(Genre::query()->whereIn('id', $genreCounts->keys())->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug'])
                    ->map(fn (Genre $genre): array => ['slug' => $genre->slug, 'name' => $genre->name, 'count' => (int) $genreCounts[$genre->id]])->all()),
                'types' => array_values(VideoType::query()->whereIn('id', $typeCounts->keys())->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug'])
                    ->map(fn (VideoType $type): array => ['slug' => $type->slug, 'name' => $type->name, 'count' => (int) $typeCounts[$type->id]])->all()),
                'agencies' => array_values(ProductionAgency::query()->whereIn('id', $agencyCounts->keys())->orderBy('name')->get(['id', 'name', 'acronym'])
                    ->map(fn (ProductionAgency $agency): array => ['id' => $agency->id, 'name' => $agency->name, 'acronym' => $agency->acronym, 'count' => (int) $agencyCounts[$agency->id]])->all()),
                'regions' => array_values(Region::query()->whereIn('id', $regionCounts->keys())->orderBy('id')->get(['id', 'name', 'description'])
                    ->map(fn (Region $region): array => ['id' => $region->id, 'name' => $region->name, 'description' => $region->description, 'count' => (int) $regionCounts[$region->id]])->all()),
                'projects' => array_values(Project::query()->whereIn('id', $projectCounts->keys())->orderByDesc('date')->orderBy('title')->limit(self::PROJECT_OPTIONS)->get(['id', 'title'])
                    ->map(fn (Project $project): array => ['id' => $project->id, 'title' => $project->title, 'count' => (int) $projectCounts[$project->id]])->all()),
                'years' => $this->years(),
            ];
        });
    }

    /**
     * Months of one year that have public records (for the year → month
     * timeline).
     *
     * @return list<array{month: int, count: int}>
     */
    public function months(int $year): array
    {
        return $this->cache->remember("months:{$year}", function () use ($year): array {
            $month = $this->datePart('month');

            return array_values($this->recordsInRange("{$year}-01-01", ($year + 1).'-01-01')
                ->selectRaw("{$month} as month, COUNT(*) as aggregate")
                ->groupByRaw($month)
                ->orderByRaw($month)
                ->toBase()
                ->get()
                ->map(fn (object $row): array => ['month' => (int) $row->month, 'count' => (int) $row->aggregate])
                ->all());
        });
    }

    /**
     * A project's public page; null when none of its records are public (so
     * internal projects stay invisible).
     *
     * @return array<string, mixed>|null
     */
    public function project(Project $project, ?string $seriesId, int $page): ?array
    {
        return $this->cache->remember("project:{$project->id}:{$seriesId}:{$page}", function () use ($project, $seriesId, $page): ?array {
            $base = $this->records()->where('records.project_id', $project->id);

            if (! (clone $base)->exists()) {
                return null;
            }

            $project->loadMissing(['agency:id,name,acronym', 'barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name']);

            $records = $base
                ->when($seriesId, fn (Builder $query, string $id) => $query->where('records.project_series_id', $id))
                ->paginate(self::PER_PAGE, page: $page)
                ->withQueryString();

            return [
                'project' => [
                    ...$this->projectSummary($project),
                    'description' => $project->description,
                    'agency' => $project->agency?->name,
                    'place' => $project->placeLabel(),
                    'location' => $project->location,
                ],
                'series' => $this->seriesOptions($project->id),
                'records' => [...$records->toArray(), 'data' => $this->cards($records->items())],
            ];
        });
    }

    /**
     * The series of a project that have public records, with their counts
     * (the project page's tabs and the browse page's series filter).
     *
     * @return list<array{id: string, title: string, date: string|null, end_date: string|null, count: int}>
     */
    public function seriesOptions(string $projectId): array
    {
        return $this->cache->remember("series-options:{$projectId}", function () use ($projectId): array {
            $counts = $this->publicBase()
                ->select('records.project_series_id')
                ->selectRaw('COUNT(*) as aggregate')
                ->where('records.project_id', $projectId)
                ->whereNotNull('records.project_series_id')
                ->groupBy('records.project_series_id')
                ->toBase()
                ->pluck('aggregate', 'project_series_id');

            return array_values(ProjectSeries::query()
                ->where('project_id', $projectId)
                ->whereIn('id', $counts->keys())
                ->orderBy('date')
                ->orderBy('title')
                ->get(['id', 'project_id', 'title', 'date', 'end_date'])
                ->map(fn (ProjectSeries $series): array => [
                    'id' => $series->id,
                    'title' => $series->title,
                    'date' => $series->date?->toDateString(),
                    'end_date' => $series->end_date?->toDateString(),
                    'count' => (int) $counts[$series->id],
                ])
                ->all());
        });
    }

    /**
     * A series' public page: the series, its project and its public records.
     *
     * @return array<string, mixed>|null
     */
    public function series(ProjectSeries $series, int $page): ?array
    {
        return $this->cache->remember("series:{$series->id}:{$page}", function () use ($series, $page): ?array {
            $base = $this->records()->where('records.project_series_id', $series->id);

            if (! (clone $base)->exists()) {
                return null;
            }

            $series->loadMissing(['project', 'barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name']);
            $records = $base->paginate(self::PER_PAGE, page: $page)->withQueryString();

            return [
                'series' => [
                    'id' => $series->id,
                    'title' => $series->title,
                    'description' => $series->description,
                    'date' => $series->date?->toDateString(),
                    'end_date' => $series->end_date?->toDateString(),
                    'place' => $series->placeLabel(),
                    'location' => $series->location,
                ],
                'project' => $series->project === null ? null : $this->projectSummary($series->project),
                'records' => [...$records->toArray(), 'data' => $this->cards($records->items())],
            ];
        });
    }

    /**
     * The watch page's public fields. The caller has already checked
     * `distribute`.
     *
     * @return array<string, mixed>
     */
    public function watch(Record $record): array
    {
        return $this->cache->remember("watch:{$record->id}", function () use ($record): array {
            $record->load([
                'project:id,title,date,end_date,thumbnail_path',
                'projectSeries:id,title',
                'agency:id,name,acronym',
                'division:id,name',
                'genre:id,name,slug',
                'videoType:id,name,slug',
                'rights',
                'contributors:id,record_id,role,name,sort_order',
                'subjects' => fn ($query) => $query->where('is_confirmed', true)->orderBy('subject')->select(['id', 'record_id', 'subject', 'type']),
                'keywords' => fn ($query) => $query->where('is_confirmed', true)->orderBy('keyword')->select(['id', 'record_id', 'keyword']),
                'transcripts' => fn ($query) => $query->where('status', TaskStatus::Done)->select(['id', 'record_id', 'language', 'is_original', 'status']),
                'activeMetadata:id,record_id,payload,is_active',
                'masterFile:id,record_id,role',
                'masterFile.technicalMetadata:id,record_file_id,duration',
                'barangay:id,name', 'city:id,name', 'province:id,name', 'region:id,name',
            ]);

            /** @var array<string, mixed> $payload */
            $payload = $record->activeMetadata->payload ?? [];
            $original = $record->transcripts->firstWhere('is_original', true);
            $transcript = $original === null ? ['language' => null, 'lines' => []] : TranscriptLines::for($record);

            return [
                'record' => [
                    'id' => $record->id,
                    'title' => $record->title,
                    'alternative_title' => $record->alternative_title,
                    'description' => $record->description ?? (is_string($payload['description'] ?? null) ? $payload['description'] : null),
                    'date_recorded' => $record->date_recorded?->toDateString(),
                    'date_recorded_precision' => $record->date_recorded_precision?->value,
                    'published_at' => $record->published_at?->toIso8601String(),
                    'duration' => $record->masterFile?->technicalMetadata?->duration,
                    'language' => $record->language,
                    'project' => $record->project === null ? null : ['id' => $record->project->id, 'title' => $record->project->title],
                    'series' => $record->projectSeries === null ? null : ['id' => $record->projectSeries->id, 'title' => $record->projectSeries->title],
                    'genre' => $record->genre === null ? null : ['slug' => $record->genre->slug, 'name' => $record->genre->name],
                    'video_type' => $record->videoType === null ? null : ['slug' => $record->videoType->slug, 'name' => $record->videoType->name],
                    'agency' => $record->agency === null ? null : ['id' => $record->agency->id, 'name' => $record->agency->name, 'acronym' => $record->agency->acronym],
                    'division' => $record->division?->name,
                    'place' => $record->placeLabel(),
                    'location' => $record->location,
                    'subjects' => $record->subjects->map(fn (RecordSubject $subject): array => ['subject' => $subject->subject, 'type' => $subject->type->value])->values()->all(),
                    'keywords' => $record->keywords->map(fn (RecordKeyword $keyword): string => $keyword->keyword)->values()->all(),
                    'credits' => $record->contributors->map(fn (RecordContributor $contributor): array => ['role' => $contributor->role->label(), 'name' => $contributor->name])->values()->all(),
                    'chapters' => $this->chapters($payload),
                    'license' => $record->rights?->license,
                    'license_url' => $record->rights?->license_url,
                    'usage_restrictions' => $record->rights?->usage_restrictions,
                    'thumbnail' => self::thumbnailUrl($record),
                ],
                'transcript' => [
                    'language' => $transcript['language'],
                    'lines' => array_map(fn (array $line): array => ['start' => $line['start'], 'end' => $line['end'], 'speaker' => $line['speaker'], 'text' => $line['text']], $transcript['lines']),
                ],
                // Key frames with what they show; no face counts on the public site.
                'frames' => array_values($record->frames()
                    ->get(['id', 'record_id', 'time_sec', 'description', 'ocr_text', 'objects'])
                    ->map(fn (RecordFrame $frame): array => [
                        'id' => $frame->id,
                        'time_sec' => $frame->time_sec,
                        'url' => route('catalogue.frames.image', ['record' => $record, 'frame' => $frame]),
                        'description' => $frame->description,
                        'ocr_text' => $frame->ocr_text,
                        'objects' => array_values(array_map(fn (array $object): string => (string) $object['label'], $frame->objects ?? [])),
                    ])
                    ->all()),
                'captions' => $record->transcripts
                    ->sortByDesc('is_original')
                    ->map(fn ($item): array => ['language' => $item->language, 'is_original' => $item->is_original])
                    ->values()
                    ->all(),
                'related' => $record->project_id === null ? [] : $this->cards($this->records()
                    ->where('records.project_id', $record->project_id)
                    ->where('records.id', '!=', $record->id)
                    ->limit(self::RELATED)
                    ->get()),
            ];
        });
    }

    /**
     * The record's own thumbnail (Phase 5), else its project's cover. The
     * version parameter changes with the image, so browsers can cache it.
     */
    public static function thumbnailUrl(Record $record): ?string
    {
        if ($record->thumbnail_path !== null) {
            return route('catalogue.thumbnail', ['record' => $record->id, 'v' => substr(md5($record->thumbnail_path), 0, 8)]);
        }

        return $record->project?->thumbnail_path ? route('catalogue.projects.thumbnail', $record->project_id) : null;
    }

    /**
     * Public records with the catalogue date, newest first.
     *
     * @return Builder<Record>
     */
    public function records(): Builder
    {
        return $this->publicBase()
            ->select(['records.id', 'records.title', 'records.project_id', 'records.project_series_id', 'records.genre_id', 'records.video_type_id', 'records.agency_id', 'records.date_recorded', 'records.published_at', 'records.thumbnail_path'])
            ->selectRaw(self::dateExpression().' as catalogue_date')
            ->orderByRaw(self::dateExpression().' desc')
            ->orderByDesc('records.id');
    }

    /**
     * Public records joined to their project (for the catalogue date), with
     * no columns or order yet: the base of grouped counts, which PostgreSQL
     * refuses when other columns are selected.
     *
     * @return Builder<Record>
     */
    private function publicBase(): Builder
    {
        return Distribution::constrain(Record::query())
            ->leftJoin('projects', 'projects.id', '=', 'records.project_id');
    }

    /**
     * date_recorded → the project's first day → the day it was published.
     *
     * @return literal-string
     */
    public static function dateExpression(): string
    {
        return 'COALESCE(records.date_recorded, projects.date, DATE(records.published_at))';
    }

    /**
     * @param  Builder<Record>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['project'] ?? null, fn (Builder $q, string $id) => $q->where('records.project_id', $id))
            ->when($filters['series'] ?? null, fn (Builder $q, string $id) => $q->where('records.project_series_id', $id))
            ->when($filters['genre'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('genre', fn (Builder $genre) => $genre->where('slug', $slug)))
            ->when($filters['type'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('videoType', fn (Builder $type) => $type->where('slug', $slug)))
            ->when($filters['agency'] ?? null, fn (Builder $q, int $id) => $q->where(fn (Builder $agency) => $agency->where('records.agency_id', $id)->orWhere('records.division_id', $id)))
            ->when($filters['region'] ?? null, fn (Builder $q, int $id) => $q->where('records.region_id', $id))
            ->when($filters['q'] ?? null, function (Builder $q, string $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $match) => $match
                    ->whereLike('records.title', $pattern)
                    ->orWhereLike('records.alternative_title', $pattern)
                    ->orWhereLike('records.description', $pattern)
                    ->orWhereHas('keywords', fn (Builder $keyword) => $keyword->where('is_confirmed', true)->whereLike('keyword', $pattern))
                    ->orWhereHas('subjects', fn (Builder $subject) => $subject->where('is_confirmed', true)->whereLike('subject', $pattern)));
            });

        $year = $filters['year'] ?? null;

        if ($year !== null) {
            $month = $filters['month'] ?? null;
            $start = $month === null ? sprintf('%04d-01-01', $year) : sprintf('%04d-%02d-01', $year, $month);
            $end = $month === null ? sprintf('%04d-01-01', $year + 1) : date('Y-m-d', (int) strtotime("{$start} +1 month"));

            $query->whereRaw(self::dateExpression().' >= ?', [$start])->whereRaw(self::dateExpression().' < ?', [$end]);
        }
    }

    /**
     * @param  Builder<Record>  $query
     */
    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'date_asc' => $query->reorder()->orderByRaw(self::dateExpression().' asc')->orderBy('records.id'),
            'title' => $query->reorder()->orderBy('records.title')->orderBy('records.id'),
            default => null,
        };
    }

    /**
     * @return Builder<Record>
     */
    private function recordsInRange(string $start, string $end): Builder
    {
        return $this->publicBase()
            ->whereRaw(self::dateExpression().' >= ?', [$start])
            ->whereRaw(self::dateExpression().' < ?', [$end]);
    }

    /**
     * @return list<array{year: int, count: int}>
     */
    private function years(): array
    {
        $year = $this->datePart('year');

        return array_values($this->publicBase()
            ->selectRaw("{$year} as year, COUNT(*) as aggregate")
            ->groupByRaw($year)
            ->orderByRaw("{$year} desc")
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['year' => (int) $row->year, 'count' => (int) $row->aggregate])
            ->all());
    }

    /**
     * The year or month of the catalogue date, per database driver. Built
     * from constants only, never from input.
     *
     * @param  'year'|'month'  $part
     * @return literal-string
     */
    private function datePart(string $part): string
    {
        $date = self::dateExpression();
        $postgres = DB::connection()->getDriverName() === 'pgsql';

        return match (true) {
            $postgres && $part === 'year' => "CAST(EXTRACT(YEAR FROM {$date}) AS INTEGER)",
            $postgres => "CAST(EXTRACT(MONTH FROM {$date}) AS INTEGER)",
            $part === 'year' => "CAST(strftime('%Y', {$date}) AS INTEGER)",
            default => "CAST(strftime('%m', {$date}) AS INTEGER)",
        };
    }

    /**
     * Grid cards for a set of public records (one query per relation).
     *
     * @param  iterable<Record>  $records
     * @return list<array<string, mixed>>
     */
    private function cards(iterable $records): array
    {
        $records = EloquentCollection::make($records);

        if ($records->isEmpty()) {
            return [];
        }

        $records->load([
            'project:id,title,thumbnail_path',
            'projectSeries:id,title',
            'genre:id,name',
            'videoType:id,name',
            'agency:id,name,acronym',
            'masterFile:id,record_id,role',
            'masterFile.technicalMetadata:id,record_file_id,duration',
        ]);

        return array_values($records->map(fn (Record $record): array => [
            'id' => $record->id,
            'title' => $record->title,
            'date' => substr((string) $record->getAttribute('catalogue_date'), 0, 10) ?: null,
            'project' => $record->project?->title,
            'series' => $record->projectSeries?->title,
            'genre' => $record->genre?->name,
            'video_type' => $record->videoType?->name,
            'agency' => $record->agency?->acronym ?: $record->agency?->name,
            'duration' => $record->masterFile?->technicalMetadata?->duration,
            'thumbnail' => self::thumbnailUrl($record),
        ])->all());
    }

    /**
     * Projects with public records, most recent first; "current" when today
     * falls within their dates.
     *
     * @return list<array<string, mixed>>
     */
    private function projectCards(int $limit): array
    {
        $counts = $this->publicBase()
            ->select('records.project_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->whereNotNull('records.project_id')
            ->groupBy('records.project_id')
            ->toBase()
            ->pluck('aggregate', 'project_id');

        return array_values(Project::query()
            ->whereIn('id', $counts->keys())
            ->orderByDesc('date')
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'date', 'end_date', 'thumbnail_path'])
            ->map(fn (Project $project): array => [...$this->projectSummary($project), 'count' => (int) $counts[$project->id]])
            ->all());
    }

    /**
     * @return array{id: string, title: string, date: string|null, end_date: string|null, is_current: bool, thumbnail: string|null}
     */
    private function projectSummary(Project $project): array
    {
        $today = now()->toDateString();
        $start = $project->date?->toDateString();
        $end = $project->end_date?->toDateString() ?? $start;

        return [
            'id' => $project->id,
            'title' => $project->title,
            'date' => $start,
            'end_date' => $project->end_date?->toDateString(),
            'is_current' => $start !== null && $start <= $today && $end >= $today,
            'thumbnail' => $project->thumbnail_path ? route('catalogue.projects.thumbnail', $project->id) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{start: float, title: string}>
     */
    private function chapters(array $payload): array
    {
        $chapters = is_array($payload['chapters'] ?? null) ? $payload['chapters'] : [];

        return array_values(array_filter(array_map(
            fn (mixed $chapter): ?array => is_array($chapter) && is_numeric($chapter['start'] ?? null) && is_string($chapter['title'] ?? null)
                ? ['start' => (float) $chapter['start'], 'title' => $chapter['title']]
                : null,
            $chapters,
        )));
    }
}
