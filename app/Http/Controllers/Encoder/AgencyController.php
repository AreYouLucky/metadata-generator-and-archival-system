<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Models\ProductionAgency;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Agencies page: producing agencies and their production units. Every
 * encoder can browse it; only admins manage it (through the admin routes).
 */
class AgencyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProductionAgency::class);

        /** @var User $user */
        $user = $request->user();

        $agencies = ProductionAgency::query()
            ->whereNull('parent_id')
            ->withCount(['records', 'projects'])
            ->with(['units' => fn (HasMany $query) => $query->orderBy('name')->withCount('divisionRecords')])
            ->orderBy('name')
            ->get(['id', 'name', 'acronym'])
            ->map(fn (ProductionAgency $agency): array => [
                ...$agency->summary(),
                'records_count' => $agency->records_count ?? 0,
                'projects_count' => $agency->projects_count ?? 0,
                'units' => $agency->units->map(fn (ProductionAgency $unit): array => [
                    ...$unit->summary(),
                    'records_count' => $unit->division_records_count ?? 0,
                ])->values()->all(),
            ]);

        return Inertia::render('encoder/agencies-page/agencies', [
            'agencies' => $agencies,
            'canManage' => $user->can('create', ProductionAgency::class),
        ]);
    }
}
