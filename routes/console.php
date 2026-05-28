<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recompute the crowdsourced digit-template consensus once an hour so the
// /api/v1/templates/digits zip is fresh without paying the cost on every GET.
Schedule::command('digits:rebuild-consensus')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Recompute item usage_count nightly so the autocomplete ranking stays
// fresh (most-used items float to the top instead of buried junk).
Schedule::command('items:recompute-usage')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();
