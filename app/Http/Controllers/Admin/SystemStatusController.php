<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SystemStatusController extends Controller
{
    /**
     * Phase 0 diagnostics: AI service health, the realtime round trip and a
     * link to Horizon.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/system-status-page/system-status', [
            'horizonUrl' => url((string) config('horizon.path')),
        ]);
    }
}
