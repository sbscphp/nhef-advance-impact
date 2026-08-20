<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Off-peak, so a burst of gateway calls doesn't compete with donor-facing traffic. Requires the
// Laravel scheduler's cron entry (`* * * * * php artisan schedule:run`) to be registered on the
// server, not something that can be verified from the codebase alone.
Schedule::command('donations:charge-recurring')->dailyAt('02:00');
