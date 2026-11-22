<?php

namespace App\Http\Controllers\All;

use App\Http\Controllers\Controller;
use App\Models\Record;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Fortify sends every role here after login. Encoders and admins go to
     * their record history; public viewers go to the catalogue.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()?->can('viewAny', Record::class)) {
            return to_route('records.index');
        }

        return to_route('catalogue.home');
    }
}
