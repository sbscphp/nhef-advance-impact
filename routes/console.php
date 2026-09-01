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

// Early enough that a today-due instance exists before the reminder command runs.
Schedule::command('communications:generate-recurring-tasks')->dailyAt('00:30');
Schedule::command('communications:send-task-reminders')->dailyAt('07:00');

// Nigeria's bank list barely changes; weekly keeps it fresh without hammering Paystack daily.
Schedule::command('banks:sync')->weeklyOn(1, '03:00');
