<?php

/**
 * Signed callbacks from the Python AI service. Loaded from bootstrap/app.php
 * under the `internal` middleware group and the /internal prefix.
 */

use App\Enums\PipelineStage;
use App\Http\Controllers\Internal\AudioExportCallbackController;
use App\Http\Controllers\Internal\PingController;
use App\Http\Controllers\Internal\RecordFileFixityController;
use App\Http\Controllers\Internal\RecordFileResultController;
use App\Http\Controllers\Internal\RecordProgressController;
use App\Http\Controllers\Internal\RecordStageResultController;
use App\Http\Controllers\Internal\TranslationCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('ping', PingController::class)->name('ping');

Route::post('records/{record}/progress', RecordProgressController::class)->name('records.progress');

Route::post('records/{record}/results/{stage}', RecordStageResultController::class)
    ->whereIn('stage', array_map(fn (PipelineStage $stage): string => $stage->value, PipelineStage::active()))
    ->name('records.results');

// {transcript} is scoped to the record (it must be one of its transcripts).
Route::post('records/{record}/translations/{transcript}/progress', [TranslationCallbackController::class, 'progress'])
    ->scopeBindings()
    ->name('records.translations.progress');
Route::post('records/{record}/translations/{transcript}/result', [TranslationCallbackController::class, 'result'])
    ->scopeBindings()
    ->name('records.translations.result');

// Audio exports (MP3 or WAV of the master's audio).
Route::post('records/{record}/audio-export', AudioExportCallbackController::class)->name('records.audio-export');

// Added files (mezzanine, access copy) and fixity checks (Phase 3).
Route::post('record-files/{recordFile}/results/{stage}', RecordFileResultController::class)
    ->whereIn('stage', ['checksum', 'probe', 'failed'])
    ->name('record-files.results');
Route::post('record-files/{recordFile}/fixity', RecordFileFixityController::class)->name('record-files.fixity');
