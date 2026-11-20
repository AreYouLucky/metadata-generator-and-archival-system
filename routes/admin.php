<?php

/**
 * Admin-only routes. Admins reach encoder features through routes/encoder.php
 * (`role:encoder,admin`), so those pages are not duplicated here.
 */

use App\Http\Controllers\Admin\AiServiceHealthController;
use App\Http\Controllers\Admin\SystemPingController;
use App\Http\Controllers\Admin\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('system', SystemStatusController::class)->name('system.show');

        Route::get('system/ai-health', AiServiceHealthController::class)
            ->middleware('throttle:30,1')
            ->name('system.ai-health');

        Route::post('system/ping', SystemPingController::class)
            ->middleware('throttle:10,1')
            ->name('system.ping');
    });
