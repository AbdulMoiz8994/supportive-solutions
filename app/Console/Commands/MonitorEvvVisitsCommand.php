<?php

namespace App\Console\Commands;

use App\Services\EvvMonitorService;
use Illuminate\Console\Command;

class MonitorEvvVisitsCommand extends Command
{
    protected $signature = 'evv:monitor {--organization= : Limit to one organization id}';

    protected $description = 'Visit/EVV Monitor Agent: mark no-shows, flag review visits, open follow-up tasks';

    public function handle(EvvMonitorService $monitor): int
    {
        $orgId = $this->option('organization') ? (int) $this->option('organization') : null;
        $result = $monitor->run($orgId);

        $this->info(sprintf(
            'EVV monitor finished — missed: %d, newly flagged: %d, review tasks: %d, auto-billable: %d',
            $result['missed'],
            $result['flagged'],
            $result['reviewTasks'],
            $result['billable'],
        ));

        return self::SUCCESS;
    }
}
