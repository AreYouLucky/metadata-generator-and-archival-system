<?php

namespace App\Services\Lookups;

use App\Models\Genre;
use App\Models\Record;
use App\Models\VideoType;
use Illuminate\Support\Str;

/**
 * The admin-managed genre and video type lists (PROJECT_SPEC §8): name, a
 * unique URL slug (used by the public browse filters) and a sort order.
 */
class LookupList
{
    /**
     * Every item in display order, with how many records use it.
     *
     * @param  class-string<Genre|VideoType>  $model
     * @return list<array{id: int, name: string, slug: string, sort_order: int, records_count: int}>
     */
    public function all(string $model): array
    {
        return array_values($model::query()
            ->select(['id', 'name', 'slug', 'sort_order'])
            ->withCount('records')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Genre|VideoType $item): array => self::present($item))
            ->all());
    }

    /**
     * @param  class-string<Genre|VideoType>  $model
     * @param  array{name: string, sort_order?: int|null}  $data
     */
    public function create(string $model, array $data): Genre|VideoType
    {
        $item = new $model;
        $item->name = $data['name'];
        $item->sort_order = $data['sort_order'] ?? 0;
        $item->slug = $this->uniqueSlug($model, $data['name']);
        $item->save();

        return $item;
    }

    /**
     * Renaming keeps the slug, so public links to the filter keep working.
     *
     * @param  array{name: string, sort_order?: int|null}  $data
     */
    public function update(Genre|VideoType $item, array $data): Genre|VideoType
    {
        $item->name = $data['name'];
        $item->sort_order = $data['sort_order'] ?? $item->sort_order;
        $item->save();

        return $item;
    }

    /**
     * Only an unused item can be deleted, so no record silently loses it.
     */
    public function isInUse(Genre|VideoType $item): bool
    {
        $column = $item instanceof Genre ? 'genre_id' : 'video_type_id';

        return Record::withTrashed()->where($column, $item->id)->exists();
    }

    /**
     * @return array{id: int, name: string, slug: string, sort_order: int, records_count: int}
     */
    public static function present(Genre|VideoType $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'sort_order' => $item->sort_order,
            'records_count' => (int) ($item->records_count ?? 0),
        ];
    }

    /**
     * @param  class-string<Genre|VideoType>  $model
     */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'item', 90, '');
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
