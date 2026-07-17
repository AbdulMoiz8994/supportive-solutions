<?php

use App\Jobs\GenerateMonthlyClaimsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('communications:sync-inbound')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('reports:run-scheduled')->hourly()->withoutOverlapping();
Schedule::command('evv:monitor')->everyFifteenMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('evv:suggest-fixes')->everyThirtyMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('forms:generate-drafts')->dailyAt('05:00')->withoutOverlapping()->runInBackground();
Schedule::command('tasks:remind-overdue')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('data-exploration:email-scheduled-views')->daily()->withoutOverlapping();

// Monthly automation cycle (client review D2 / D4 / D10). All three commands
// are idempotent, so a missed or repeated run is safe. runInBackground keeps
// a long claim run or call batch from blocking the scheduler tick.
Schedule::job(new GenerateMonthlyClaimsJob)
    ->monthlyOn(1, '02:00')
    ->name('billing:generate-claims')
    ->withoutOverlapping();
Schedule::command('background-checks:run-batch')->monthlyOn(1, '03:00')->withoutOverlapping()->runInBackground();
Schedule::command('wellness:place-monthly-calls')->monthlyOn(2, '10:00')->withoutOverlapping()->runInBackground();
Schedule::command('hha:sync-evv')->hourly()->withoutOverlapping()->runInBackground();
