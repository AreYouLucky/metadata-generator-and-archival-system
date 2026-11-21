<?php

/**
 * Signed callbacks from the Python AI service. Loaded from bootstrap/app.php
 * under the `internal` middleware group and the /internal prefix.
 */

use App\Enums\PipelineStage;
use App\Http\Controllers\Internal\PingController;
use App\Http\Controllers\Internal\RecordProgressController;
use App\Http\Controllers\Internal\RecordStageResultController;
use Illuminate\Support\Facades\Route;

Route::post('ping', PingController::class)->name('ping');

Route::post('records/{record}/progress', RecordProgressController::class)->name('records.progress');

Route::post('records/{record}/results/{stage}', RecordStageResultController::class)
    ->whereIn('stage', array_map(fn (PipelineStage $stage): string => $stage->value, PipelineStage::active()))
    ->name('records.results');
