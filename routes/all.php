<?php

/**
 * Routes shared by every signed-in role.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'all/dashboard-page/dashboard')->name('dashboard');
});
