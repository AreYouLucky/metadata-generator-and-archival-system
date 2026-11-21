<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', fn () => Inertia::render('public/welcome-page/welcome', [
    'canRegister' => Features::enabled(Features::registration()),
]))->name('home');

require __DIR__.'/all.php';
require __DIR__.'/admin.php';
require __DIR__.'/encoder.php';
require __DIR__.'/user.php';
require __DIR__.'/settings.php';
