<?php

/**
 * Signed callbacks from the Python AI service. Loaded from bootstrap/app.php
 * under the `internal` middleware group and the /internal prefix.
 */

use App\Http\Controllers\Internal\PingController;
use Illuminate\Support\Facades\Route;

Route::post('ping', PingController::class)->name('ping');
