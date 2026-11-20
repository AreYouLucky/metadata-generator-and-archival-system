<?php

/**
 * Encoder routes (records, projects, uploads). Admins pass `role:encoder,admin`.
 * Filled in from Phase 1.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:encoder,admin'])
    ->prefix('encoder')
    ->name('encoder.')
    ->group(function () {
        //
    });
