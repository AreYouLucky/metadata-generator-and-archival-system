<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgencyRequest;
use App\Http\Requests\Admin\UpdateAgencyRequest;
use App\Models\ProductionAgency;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Admin management of producing agencies and their production units
 * (PROJECT_SPEC §8 `/admin/agencies`, pulled forward from Phase 4). The page
 * itself is Encoder\AgencyController, so encoders can browse the list.
 */
class AgencyController extends Controller
{
    public function store(StoreAgencyRequest $request): JsonResponse
    {
        $agency = ProductionAgency::create($request->validated());

        return response()->json($agency->summary(), 201);
    }

    public function update(UpdateAgencyRequest $request, ProductionAgency $agency): JsonResponse
    {
        $agency->update($request->validated());

        return response()->json($agency->summary());
    }

    /**
     * Only an unused agency or unit can be deleted: deleting one that records,
     * projects or units refer to would silently erase archival credits.
     */
    public function destroy(ProductionAgency $agency): JsonResponse
    {
        Gate::authorize('delete', $agency);

        $inUse = $agency->units()->exists()
            || Record::query()->where('agency_id', $agency->id)->orWhere('division_id', $agency->id)->exists()
            || Project::query()->where('agency_id', $agency->id)->exists()
            || ProjectSeries::query()->where('agency_id', $agency->id)->exists();

        abort_if($inUse, 422, 'This is still used by units, projects or records, so it cannot be deleted. Rename it instead.');

        $agency->delete();

        return response()->json(['deleted' => true]);
    }
}
