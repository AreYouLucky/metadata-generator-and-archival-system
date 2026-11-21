<?php

namespace App\Http\Controllers\All;

use App\Http\Controllers\Controller;
use App\Models\Record;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Fortify sends every role here after login. Encoders and admins go to their
     * record history; viewers get the placeholder until the public catalogue
     * arrives in Phase 4.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->can('viewAny', Record::class)) {
            return to_route('records.index');
        }

        return Inertia::render('all/dashboard-page/dashboard');
    }
}
