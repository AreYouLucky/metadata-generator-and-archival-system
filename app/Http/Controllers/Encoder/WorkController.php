<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreWorkRequest;
use App\Http\Requests\Encoder\UpdateWorkRequest;
use App\Models\Record;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Works: the reels or parts of one production, each part its own record.
 * A record joins a work through PATCH /records/{record} (work_id, part).
 */
class WorkController extends Controller
{
    public function store(StoreWorkRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $work = new Work($request->validated());
        $work->created_by = $user->id;
        $work->save();

        return response()->json(['work' => self::present($work)], 201);
    }

    public function show(Work $work): JsonResponse
    {
        Gate::authorize('view', $work);

        return response()->json(['work' => self::present($work)]);
    }

    public function update(UpdateWorkRequest $request, Work $work): JsonResponse
    {
        $work->update($request->validated());

        return response()->json(['work' => self::present($work)]);
    }

    /**
     * @return array{id: string, title: string, total_parts: int|null, parts: list<array{id: string, title: string, part_number: int|null, part_label: string|null}>}
     */
    public static function present(Work $work): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'total_parts' => $work->total_parts,
            'parts' => array_values($work->records()
                ->orderBy('part_number')
                ->get(['id', 'work_id', 'title', 'part_number', 'part_label'])
                ->map(fn (Record $part): array => [
                    'id' => $part->id,
                    'title' => $part->title,
                    'part_number' => $part->part_number,
                    'part_label' => $part->part_label,
                ])
                ->all()),
        ];
    }
}
