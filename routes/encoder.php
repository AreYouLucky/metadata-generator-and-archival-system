<?php

/**
 * Encoder routes (records, uploads, the workspace). Admins pass
 * `role:encoder,admin`, so they use these same routes and pages. URLs follow
 * PROJECT_SPEC §8 (no /encoder prefix).
 */

use App\Http\Controllers\Encoder\AgencyController;
use App\Http\Controllers\Encoder\AgencyOptionsController;
use App\Http\Controllers\Encoder\AttachmentController;
use App\Http\Controllers\Encoder\AudioExportController;
use App\Http\Controllers\Encoder\CancelProcessingController;
use App\Http\Controllers\Encoder\CaptionController;
use App\Http\Controllers\Encoder\ConfirmSuggestionsController;
use App\Http\Controllers\Encoder\ContributorController;
use App\Http\Controllers\Encoder\DescriptiveSuggestionController;
use App\Http\Controllers\Encoder\ExportSchemaController;
use App\Http\Controllers\Encoder\FixityController;
use App\Http\Controllers\Encoder\KeywordController;
use App\Http\Controllers\Encoder\MetadataController;
use App\Http\Controllers\Encoder\ProjectController;
use App\Http\Controllers\Encoder\ProjectOptionsController;
use App\Http\Controllers\Encoder\ProjectSeriesController;
use App\Http\Controllers\Encoder\ProvenanceController;
use App\Http\Controllers\Encoder\RecordController;
use App\Http\Controllers\Encoder\RecordExportController;
use App\Http\Controllers\Encoder\RecordFileController;
use App\Http\Controllers\Encoder\RecordFrameImageController;
use App\Http\Controllers\Encoder\RecordMediaController;
use App\Http\Controllers\Encoder\RecordWaveformController;
use App\Http\Controllers\Encoder\RetryProcessingController;
use App\Http\Controllers\Encoder\ReviewController;
use App\Http\Controllers\Encoder\RightsController;
use App\Http\Controllers\Encoder\SceneController;
use App\Http\Controllers\Encoder\SegmentController;
use App\Http\Controllers\Encoder\SpeakerController;
use App\Http\Controllers\Encoder\SubjectController;
use App\Http\Controllers\Encoder\SubmitRecordController;
use App\Http\Controllers\Encoder\ThumbnailController;
use App\Http\Controllers\Encoder\TitleController;
use App\Http\Controllers\Encoder\TranslationController;
use App\Http\Controllers\Encoder\UploadController;
use App\Http\Controllers\Encoder\WorkController;
use Illuminate\Support\Facades\Route;

// `catalogue.refresh` clears the public catalogue cache after an admin edits an
// approved record, or anyone edits a project or series.
Route::middleware(['auth', 'verified', 'role:encoder,admin', 'catalogue.refresh'])->group(function () {
    Route::get('records', [RecordController::class, 'index'])->name('records.index');
    Route::get('records/create', [RecordController::class, 'create'])->name('records.create');
    Route::post('records', [RecordController::class, 'store'])->middleware('throttle:ai')->name('records.store');

    Route::middleware('throttle:uploads')->prefix('records/uploads')->name('records.uploads.')->group(function () {
        Route::post('/', [UploadController::class, 'store'])->name('store');
        Route::get('{upload}', [UploadController::class, 'show'])->whereUuid('upload')->name('show');
        Route::post('{upload}/chunks', [UploadController::class, 'chunk'])->whereUuid('upload')->name('chunk');
    });

    Route::get('records/{record}', [RecordController::class, 'show'])->name('records.show');
    Route::patch('records/{record}', [RecordController::class, 'update'])->middleware('throttle:60,1')->name('records.update');
    Route::delete('records/{record}', [RecordController::class, 'destroy'])->middleware('throttle:30,1')->name('records.destroy');
    Route::get('records/{record}/media', RecordMediaController::class)->name('records.media');
    Route::get('records/{record}/waveform', RecordWaveformController::class)->name('records.waveform');
    Route::get('records/{record}/export', RecordExportController::class)->name('records.export');
    // Audio as MP3 or WAV, made from the master in the background, then downloaded.
    Route::post('records/{record}/audio-export', [AudioExportController::class, 'store'])->middleware('throttle:10,1')->name('records.audio-export.store');
    Route::get('records/{record}/audio-export/{format}', [AudioExportController::class, 'show'])->name('records.audio-export.show');
    Route::post('records/{record}/retry', RetryProcessingController::class)->middleware('throttle:ai')->name('records.retry');
    Route::post('records/{record}/cancel', CancelProcessingController::class)->middleware('throttle:30,1')->name('records.cancel');
    Route::post('records/{record}/submit', SubmitRecordController::class)->middleware('throttle:30,1')->name('records.submit');

    Route::patch('records/{record}/transcript/speaker', [SegmentController::class, 'assignSpeaker'])->name('records.transcript.speaker');
    Route::patch('records/{record}/segments/{segment}', [SegmentController::class, 'update'])->name('records.segments.update');
    Route::delete('records/{record}/segments/{segment}', [SegmentController::class, 'destroy'])->name('records.segments.destroy');

    Route::put('records/{record}/contributors', [ContributorController::class, 'sync'])->name('records.contributors.sync');

    Route::post('records/{record}/speakers', [SpeakerController::class, 'store'])->name('records.speakers.store');
    Route::patch('records/{record}/speakers/{speaker}', [SpeakerController::class, 'update'])->scopeBindings()->name('records.speakers.update');

    Route::post('records/{record}/metadata', [MetadataController::class, 'store'])->middleware('throttle:ai')->name('records.metadata.store');
    Route::patch('records/{record}/metadata/{metadata}', [MetadataController::class, 'update'])->name('records.metadata.update');
    Route::post('records/{record}/metadata/{metadata}/activate', [MetadataController::class, 'activate'])->name('records.metadata.activate');
    // Archive panel (Phase 3)
    Route::post('records/{record}/subjects', [SubjectController::class, 'store'])->name('records.subjects.store');
    Route::patch('records/{record}/subjects/{subject}', [SubjectController::class, 'update'])->scopeBindings()->name('records.subjects.update');
    Route::delete('records/{record}/subjects/{subject}', [SubjectController::class, 'destroy'])->scopeBindings()->name('records.subjects.destroy');
    Route::post('records/{record}/keywords', [KeywordController::class, 'store'])->name('records.keywords.store');
    Route::patch('records/{record}/keywords/{keyword}', [KeywordController::class, 'update'])->scopeBindings()->name('records.keywords.update');
    Route::delete('records/{record}/keywords/{keyword}', [KeywordController::class, 'destroy'])->scopeBindings()->name('records.keywords.destroy');
    Route::post('records/{record}/suggestions/confirm', ConfirmSuggestionsController::class)->middleware('throttle:30,1')->name('records.suggestions.confirm');
    Route::post('records/{record}/descriptive-suggestions', DescriptiveSuggestionController::class)->middleware('throttle:ai')->name('records.descriptive-suggestions.store');
    Route::put('records/{record}/rights', RightsController::class)->name('records.rights.update');
    Route::put('records/{record}/provenance', ProvenanceController::class)->name('records.provenance.update');
    Route::post('records/{record}/attachments', [AttachmentController::class, 'store'])->middleware('throttle:30,1')->name('records.attachments.store');
    Route::get('records/{record}/attachments/{attachment}', [AttachmentController::class, 'show'])->scopeBindings()->name('records.attachments.show');
    Route::delete('records/{record}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->scopeBindings()->name('records.attachments.destroy');

    // Visual analysis (Phase 5). A thumbnail at a time runs ffmpeg on the AI service.
    Route::post('records/{record}/thumbnail', [ThumbnailController::class, 'store'])->middleware('throttle:20,1')->name('records.thumbnail.store');
    Route::get('records/{record}/thumbnail', [ThumbnailController::class, 'show'])->name('records.thumbnail');
    Route::get('records/{record}/frames/{frame}/image', RecordFrameImageController::class)->scopeBindings()->name('records.frames.image');
    Route::post('records/{record}/scenes', [SceneController::class, 'store'])->middleware('throttle:60,1')->name('records.scenes.store');
    Route::patch('records/{record}/scenes/{scene}', [SceneController::class, 'update'])->middleware('throttle:60,1')->scopeBindings()->name('records.scenes.update');
    Route::delete('records/{record}/scenes/{scene}', [SceneController::class, 'destroy'])->middleware('throttle:60,1')->scopeBindings()->name('records.scenes.destroy');

    Route::post('records/{record}/files', [RecordFileController::class, 'store'])->middleware('throttle:ai')->name('records.files.store');
    Route::post('records/{record}/fixity', FixityController::class)->middleware('throttle:6,1')->name('records.fixity');

    Route::post('works', [WorkController::class, 'store'])->middleware('throttle:30,1')->name('works.store');
    Route::get('works/{work}', [WorkController::class, 'show'])->name('works.show');
    Route::patch('works/{work}', [WorkController::class, 'update'])->middleware('throttle:30,1')->name('works.update');

    Route::post('records/{record}/titles', TitleController::class)->middleware('throttle:ai')->name('records.titles.store');

    Route::post('records/{record}/translations', [TranslationController::class, 'store'])->middleware('throttle:ai')->name('records.translations.store');
    Route::get('records/{record}/translations/{language}', [TranslationController::class, 'show'])->name('records.translations.show');
    Route::get('records/{record}/captions/{language}', CaptionController::class)->name('records.captions');

    Route::get('settings/export-schemas', [ExportSchemaController::class, 'index'])->name('export-schemas.index');
    Route::post('settings/export-schemas', [ExportSchemaController::class, 'store'])->middleware('throttle:30,1')->name('export-schemas.store');
    Route::patch('settings/export-schemas/{exportSchema}', [ExportSchemaController::class, 'update'])->middleware('throttle:30,1')->name('export-schemas.update');
    Route::delete('settings/export-schemas/{exportSchema}', [ExportSchemaController::class, 'destroy'])->middleware('throttle:30,1')->name('export-schemas.destroy');

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
