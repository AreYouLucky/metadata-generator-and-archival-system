<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fixity checks for files not checked within FIXITY_CHECK_INTERVAL_DAYS (PROJECT_SPEC §11).
Schedule::command('archive:fixity')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// Public API tokens past their expiry date (Phase 7).
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
