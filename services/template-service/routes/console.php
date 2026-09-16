<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Template microservice running smoothly.');
})->purpose('Display an inspiring quote');

Schedule::command('templates:sync-statuses')->everyMinute();
Schedule::command('templates:sync-statuses --coded')->dailyAt('03:15');
