<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon's throughput and wait-time graphs. It needs Redis, so it is planned only while the queue is on Redis: on the
// database queue it would fail every five minutes.
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->when(static fn (): bool => config('queue.default') === 'redis');
