<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('care:sync --source=scheduler')->everyThirtyMinutes();
Schedule::command('pim:scan')->everyMinute()->withoutOverlapping()->when(fn () => config('pim.scan_enabled'));
