<?php

namespace App\Console\Commands;

use App\Services\Reports\ReportScheduleService;
use Illuminate\Console\Command;

class RunScheduledReports extends Command
{
    protected $signature = 'reports:run-scheduled';

    protected $description = 'Execute due report schedules and email recipients';

    public function handle(ReportScheduleService $schedules): int
    {
        $count = $schedules->runDueSchedules();
        $this->info("Ran {$count} scheduled report(s).");

        return self::SUCCESS;
    }
}
