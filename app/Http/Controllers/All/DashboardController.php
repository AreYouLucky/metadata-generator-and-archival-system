<?php

namespace App\Http\Controllers\All;

use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Models\User;
use App\Services\Dashboard\WorkspaceDashboard;
use App\Services\Usage\ProcessingLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Fortify sends every role here after login. Encoders and admins get the
     * workspace dashboard; public viewers go to the catalogue. Every section
     * is a closure, so a partial reload (the page polls while videos
     * process) runs only the queries it asks for.
     */
    public function __invoke(Request $request, ProcessingLimit $processingLimit): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('viewAny', Record::class)) {
            return to_route('catalogue.home');
        }

        $dashboard = new WorkspaceDashboard($user, $processingLimit);

        return Inertia::render('encoder/dashboard-page/dashboard', [
            'isAdmin' => $user->isAdmin(),
            'stats' => fn (): array => $dashboard->stats(),
            'activity' => fn (): array => $dashboard->activity(),
            'attention' => fn (): array => $dashboard->attention(),
            'processing' => fn (): array => $dashboard->processing(),
            'drafts' => fn (): array => $dashboard->drafts(),
            'projects' => fn (): array => $dashboard->projects(),
            'allowance' => fn (): ?array => $dashboard->allowance(),
        ]);
    }
}
