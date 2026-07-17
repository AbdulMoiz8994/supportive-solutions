<?php

namespace App\Console\Commands;

use App\Services\Communication\WellnessCallService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PlaceWellnessCallsCommand extends Command
{
    protected $signature = 'wellness:place-monthly-calls
        {--org= : Only call clients of one organization ID}
        {--period= : Period as YYYY-MM (defaults to the current month)}';

    protected $description = 'Place the monthly AI wellness calls to active clients (idempotent per client per month)';

    public function handle(WellnessCallService $wellness): int
    {
        $period = $this->option('period')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('period'))->startOfMonth()
            : now();

        $counts = $wellness->placeMonthlyCalls(
            $this->option('org') ? (int) $this->option('org') : null,
            $period,
        );

        $this->info(sprintf(
            'Wellness calls for %s — %d placed, %d already called, %d without phone, %d failed.',
            $period->format('M Y'),
            $counts['placed'],
            $counts['already_called'],
            $counts['no_phone'],
            $counts['failed'],
        ));

        return self::SUCCESS;
    }
}
