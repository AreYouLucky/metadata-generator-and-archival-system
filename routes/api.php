<?php

/**
 * Public API (Phase 7), prefixed /api/v1 (bootstrap/app.php). Sanctum bearer
 * tokens only, created by encoders and admins in Settings → API tokens; a
 * token acts with its owner's permissions and abilities. Limited per token
 * (`throttle:api`); creating a record also counts toward the AI limit.
 */

use App\Http\Controllers\Api\V1\RecordController;
use App\Http\Controllers\Api\V1\RecordExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:encoder,admin', 'throttle:api'])->name('api.v1.')->group(function () {
    Route::get('records', [RecordController::class, 'index'])->name('records.index');
    Route::post('records', [RecordController::class, 'store'])->middleware('throttle:ai')->name('records.store');
    Route::get('records/{record}', [RecordController::class, 'show'])->name('records.show');
    Route::get('records/{record}/export', RecordExportController::class)->name('records.export');
});
