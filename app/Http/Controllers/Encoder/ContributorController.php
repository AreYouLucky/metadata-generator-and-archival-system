<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SyncContributorsRequest;
use App\Models\Record;
use App\Models\RecordContributor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ContributorController extends Controller
{
    /**
     * Replace the record's credits with the submitted list, keeping the ids of
     * rows that were only edited or moved.
     */
    public function sync(SyncContributorsRequest $request, Record $record): JsonResponse
    {
        /** @var list<array{id?: int|null, role: string, name: string}> $rows */
        $rows = $request->validated('contributors');

        DB::transaction(function () use ($record, $rows): void {
            Record::query()->whereKey($record->id)->lockForUpdate()->first(['id']);

            $keptIds = array_values(array_filter(array_map(fn (array $row): ?int => $row['id'] ?? null, $rows)));
            $record->contributors()->whereNotIn('id', $keptIds)->delete();
            $existing = $record->contributors()->whereIn('id', $keptIds)->get()->keyBy('id');

            foreach ($rows as $position => $row) {
                $attributes = [
                    'role' => $row['role'],
                    'name' => trim($row['name']),
                    'sort_order' => $position,
                ];

                /** @var RecordContributor|null $contributor */
                $contributor = isset($row['id']) ? $existing->get($row['id']) : null;

                if ($contributor !== null) {
                    $contributor->update($attributes);
                } else {
                    $record->contributors()->create($attributes);
                }
            }
        });

        return response()->json(self::list($record));
    }

    /**
     * @return list<array{id: int, role: string, name: string}>
     */
    public static function list(Record $record): array
    {
        return array_values($record->contributors()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'role', 'name'])
            ->map(fn (RecordContributor $contributor): array => [
                'id' => $contributor->id,
                'role' => $contributor->role->value,
                'name' => $contributor->name,
            ])
            ->all());
    }
}
