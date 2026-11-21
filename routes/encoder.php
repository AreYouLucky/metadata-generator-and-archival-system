<?php

/**
 * Encoder routes (records, uploads, the workspace). Admins pass
 * `role:encoder,admin`, so they use these same routes and pages. URLs follow
 * PROJECT_SPEC §8 (no /encoder prefix).
 */

use App\Http\Controllers\Encoder\AgencyController;
use App\Http\Controllers\Encoder\AgencyOptionsController;
use App\Http\Controllers\Encoder\ContributorController;
use App\Http\Controllers\Encoder\MetadataController;
use App\Http\Controllers\Encoder\ProjectController;
use App\Http\Controllers\Encoder\ProjectOptionsController;
use App\Http\Controllers\Encoder\ProjectSeriesController;
use App\Http\Controllers\Encoder\RecordController;
use App\Http\Controllers\Encoder\RecordExportController;
use App\Http\Controllers\Encoder\RecordMediaController;
use App\Http\Controllers\Encoder\RecordWaveformController;
use App\Http\Controllers\Encoder\RetryProcessingController;
use App\Http\Controllers\Encoder\ReviewController;
use App\Http\Controllers\Encoder\SegmentController;
use App\Http\Controllers\Encoder\SpeakerController;
use App\Http\Controllers\Encoder\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:encoder,admin'])->group(function () {
    Route::get('records', [RecordController::class, 'index'])->name('records.index');
    Route::get('records/create', [RecordController::class, 'create'])->name('records.create');
    Route::post('records', [RecordController::class, 'store'])->middleware('throttle:ai')->name('records.store');

    Route::middleware('throttle:uploads')->prefix('records/uploads')->name('records.uploads.')->group(function () {
        Route::post('/', [UploadController::class, 'store'])->name('store');
        Route::get('{upload}', [UploadController::class, 'show'])->whereUuid('upload')->name('show');
        Route::post('{upload}/chunks', [UploadController::class, 'chunk'])->whereUuid('upload')->name('chunk');
    });

    Route::get('records/{record}', [RecordController::class, 'show'])->name('records.show');
    Route::get('records/{record}/media', RecordMediaController::class)->name('records.media');
    Route::get('records/{record}/waveform', RecordWaveformController::class)->name('records.waveform');
    Route::get('records/{record}/export', RecordExportController::class)->name('records.export');
    Route::post('records/{record}/retry', RetryProcessingController::class)->middleware('throttle:ai')->name('records.retry');

    Route::patch('records/{record}/transcript/speaker', [SegmentController::class, 'assignSpeaker'])->name('records.transcript.speaker');
    Route::patch('records/{record}/segments/{segment}', [SegmentController::class, 'update'])->name('records.segments.update');
    Route::delete('records/{record}/segments/{segment}', [SegmentController::class, 'destroy'])->name('records.segments.destroy');

    Route::put('records/{record}/contributors', [ContributorController::class, 'sync'])->name('records.contributors.sync');

    Route::post('records/{record}/speakers', [SpeakerController::class, 'store'])->name('records.speakers.store');
    Route::patch('records/{record}/speakers/{speaker}', [SpeakerController::class, 'update'])->scopeBindings()->name('records.speakers.update');

    Route::post('records/{record}/metadata', [MetadataController::class, 'store'])->middleware('throttle:ai')->name('records.metadata.store');
    Route::patch('records/{record}/metadata/{metadata}', [MetadataController::class, 'update'])->name('records.metadata.update');
    Route::post('records/{record}/metadata/{metadata}/activate', [MetadataController::class, 'activate'])->name('records.metadata.activate');

    Route::get('agencies', [AgencyController::class, 'index'])->name('agencies.index');
    Route::get('agencies/options', AgencyOptionsController::class)->name('agencies.options');

    Route::get('reviews', ReviewController::class)->name('reviews.index');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('throttle:30,1')->name('projects.store');
    Route::get('projects/options', ProjectOptionsController::class)->name('projects.options');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    // Multipart (sent as POST with _method=PATCH) because it may carry a thumbnail.
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('throttle:30,1')->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('throttle:30,1')->name('projects.destroy');
    Route::get('projects/{project}/thumbnail', [ProjectController::class, 'thumbnail'])->name('projects.thumbnail');
    Route::patch('project-series/{series}', [ProjectSeriesController::class, 'update'])->middleware('throttle:30,1')->name('project-series.update');
    Route::post('projects/{project}/series', [ProjectSeriesController::class, 'store'])->middleware('throttle:30,1')->name('projects.series.store');
});
