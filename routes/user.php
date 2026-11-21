<?php

/**
 * Public viewer routes: the catalogue at /browse (Phase 4) and the location
 * lookups. Guests can use them unless PUBLIC_CATALOGUE_REQUIRES_LOGIN is true.
 */

use App\Http\Controllers\User\LocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('archive.public_catalogue_requires_login') ? ['auth', 'throttle:120,1'] : ['throttle:120,1'])
    ->prefix('locations')
    ->name('locations.')
    ->group(function () {
        Route::get('regions', [LocationController::class, 'regions'])->name('regions');
        Route::get('provinces', [LocationController::class, 'provinces'])->name('provinces');
        Route::get('cities', [LocationController::class, 'cities'])->name('cities');
        Route::get('barangays', [LocationController::class, 'barangays'])->name('barangays');
    });
