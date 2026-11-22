<?php

/**
 * Public viewer routes: the catalogue and the location lookups. Guests can
 * use them unless PUBLIC_CATALOGUE_REQUIRES_LOGIN is true. The catalogue
 * lives under /catalogue, /browse and /watch: `/` is the landing page and
 * `/projects/{project}` is the encoders' project page.
 */

use App\Http\Controllers\User\CatalogueController;
use App\Http\Controllers\User\CatalogueMediaController;
use App\Http\Controllers\User\LocationController;
use Illuminate\Support\Facades\Route;

$requiresLogin = config('archive.public_catalogue_requires_login');

Route::middleware($requiresLogin ? ['auth', 'throttle:120,1'] : ['throttle:120,1'])
    ->prefix('locations')
    ->name('locations.')
    ->group(function () {
        Route::get('regions', [LocationController::class, 'regions'])->name('regions');
        Route::get('provinces', [LocationController::class, 'provinces'])->name('provinces');
        Route::get('cities', [LocationController::class, 'cities'])->name('cities');
        Route::get('barangays', [LocationController::class, 'barangays'])->name('barangays');
    });

Route::middleware($requiresLogin ? ['auth', 'throttle:catalogue'] : ['throttle:catalogue'])
    ->name('catalogue.')
    ->group(function () {
        Route::get('catalogue', [CatalogueController::class, 'home'])->name('home');
        Route::get('browse', [CatalogueController::class, 'browse'])->name('browse');
        Route::get('browse/projects/{project}', [CatalogueController::class, 'project'])->name('projects.show');
        Route::get('browse/series/{series}', [CatalogueController::class, 'series'])->name('series.show');
        Route::get('watch/{record}', [CatalogueController::class, 'watch'])->name('watch');
        Route::get('watch/{record}/captions/{language}', [CatalogueMediaController::class, 'captions'])->name('captions');
    });

// Media: its own, higher limit, as seeking sends many Range requests.
Route::middleware($requiresLogin ? ['auth', 'throttle:catalogue-media'] : ['throttle:catalogue-media'])
    ->name('catalogue.')
    ->group(function () {
        Route::get('watch/{record}/media', [CatalogueMediaController::class, 'stream'])->name('media');
        Route::get('watch/{record}/thumbnail', [CatalogueMediaController::class, 'thumbnail'])->name('thumbnail');
        Route::get('watch/{record}/frames/{frame}/image', [CatalogueMediaController::class, 'frame'])->scopeBindings()->name('frames.image');
        Route::get('browse/projects/{project}/thumbnail', [CatalogueMediaController::class, 'projectThumbnail'])->name('projects.thumbnail');
    });
