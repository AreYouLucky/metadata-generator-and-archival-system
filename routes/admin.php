<?php

/**
 * Admin-only routes. Admins reach encoder features through routes/encoder.php
 * (`role:encoder,admin`), so those pages are not duplicated here.
 */

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\AiServiceHealthController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\DispositionController;
use App\Http\Controllers\Admin\GenreController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\ReopenRecordController;
use App\Http\Controllers\Admin\SystemPingController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoTypeController;
use Illuminate\Support\Facades\Route;

// `catalogue.refresh` clears the public catalogue cache after edits it shows.
Route::middleware(['auth', 'verified', 'role:admin', 'catalogue.refresh'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', OverviewController::class)->name('overview');

        // Approval workflow (Phase 4)
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('approvals/{record}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('approvals/{record}', [ApprovalController::class, 'store'])->middleware('throttle:60,1')->name('approvals.store');
        Route::post('records/{record}/reopen', ReopenRecordController::class)->middleware('throttle:60,1')->name('records.reopen');
        Route::get('records/{record}/access-log', AccessLogController::class)->name('records.access-log');

        Route::get('usage', [UsageController::class, 'index'])->name('usage.index');
        Route::get('usage/export', [UsageController::class, 'export'])->middleware('throttle:10,1')->name('usage.export');

        Route::get('genres', [GenreController::class, 'index'])->name('genres.index');
        Route::post('genres', [GenreController::class, 'store'])->middleware('throttle:60,1')->name('genres.store');
        Route::patch('genres/{genre}', [GenreController::class, 'update'])->middleware('throttle:60,1')->name('genres.update');
        Route::delete('genres/{genre}', [GenreController::class, 'destroy'])->middleware('throttle:60,1')->name('genres.destroy');

        Route::get('video-types', [VideoTypeController::class, 'index'])->name('video-types.index');
        Route::post('video-types', [VideoTypeController::class, 'store'])->middleware('throttle:60,1')->name('video-types.store');
        Route::patch('video-types/{videoType}', [VideoTypeController::class, 'update'])->middleware('throttle:60,1')->name('video-types.update');
        Route::delete('video-types/{videoType}', [VideoTypeController::class, 'destroy'])->middleware('throttle:60,1')->name('video-types.destroy');

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
