<?php

/**
 * Admin-only routes. Admins reach encoder features through routes/encoder.php
 * (`role:encoder,admin`), so those pages are not duplicated here.
 */

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\AiServiceHealthController;
use App\Http\Controllers\Admin\DispositionController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\SystemPingController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UserController;
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

        Route::post('agencies', [AgencyController::class, 'store'])->middleware('throttle:60,1')->name('agencies.store');
        Route::patch('agencies/{agency}', [AgencyController::class, 'update'])->middleware('throttle:60,1')->name('agencies.update');
        Route::delete('agencies/{agency}', [AgencyController::class, 'destroy'])->middleware('throttle:60,1')->name('agencies.destroy');

        Route::get('logs', LogController::class)->name('logs.index');
        Route::get('dispositions', DispositionController::class)->name('dispositions.index');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->middleware('throttle:30,1')->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->middleware('throttle:60,1')->name('users.update');
    });
