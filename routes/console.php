<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon's throughput and wait-time graphs. Harmless without Redis: the command only runs where the scheduler does.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
