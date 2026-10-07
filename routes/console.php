<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('integrations:sync --source=scheduler')->everyTenMinutes()->withoutOverlapping();
Schedule::command('pim:scan')->everyMinute()->withoutOverlapping()->when(fn () => config('pim.scan_enabled'));
Schedule::command(sprintf(
    'atom:import-products --per-type=50 --completion-marker=%s',
    config('atom.import_marker')
))->everyMinute()->withoutOverlapping(1440)->runInBackground()
    ->when(fn () => is_file(config('atom.import_marker')));
