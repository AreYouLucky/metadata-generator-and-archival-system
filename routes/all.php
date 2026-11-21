<?php

/**
 * Routes shared by every signed-in role.
 */

use App\Http\Controllers\All\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});
