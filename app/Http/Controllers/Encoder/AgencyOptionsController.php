<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Models\ProductionAgency;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AgencyOptionsController extends Controller
{
    /**
     * Agencies with their production units, for the agency pickers. A short
     * admin-managed list, so it is sent whole.
     */
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', ProductionAgency::class);

        $agencies = ProductionAgency::query()
            ->whereNull('parent_id')
            ->with(['units' => fn (HasMany $query) => $query->orderBy('name')->select(['id', 'parent_id', 'name', 'acronym'])])
            ->orderBy('name')
            ->get(['id', 'name', 'acronym'])
            ->map(fn (ProductionAgency $agency): array => [
                ...$agency->summary(),
                'units' => $agency->units->map(fn (ProductionAgency $unit): array => $unit->summary())->values()->all(),
            ]);

        return response()->json(['data' => $agencies]);
    }
}
