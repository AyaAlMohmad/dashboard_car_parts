<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic backup every day at 2:00 AM
Schedule::command('backup:database')->dailyAt('02:00');

// Check and run user-defined backup schedules every minute
Schedule::command('backup:run-schedules')->everyMinute();
