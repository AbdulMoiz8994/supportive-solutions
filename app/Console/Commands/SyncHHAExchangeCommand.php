<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\HHA\HHASyncService;
use Illuminate\Console\Command;

class SyncHHAExchangeCommand extends Command
{
    protected $signature = 'hha:sync-evv
        {--org= : Only sync one organization ID}
        {--visits-only : Sync visits only}
        {--caregivers-only : Sync caregivers only}';

    protected $description = 'Push completed EVV visits and caregiver records to HHAeXchange';

    public function handle(HHASyncService $sync): int
    {
        $orgIds = $this->option('org')
            ? collect([(int) $this->option('org')])
            : Organization::query()->pluck('id');

        $visitTotals = ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        $caregiverTotals = ['synced' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($orgIds as $orgId) {
            if (! $this->option('visits-only')) {
                $caregivers = $sync->syncCaregivers($orgId);
                foreach ($caregivers as $key => $value) {
                    $caregiverTotals[$key] = ($caregiverTotals[$key] ?? 0) + $value;
                }
            }

            if (! $this->option('caregivers-only')) {
                $visits = $sync->syncPendingVisits($orgId);
                foreach ($visits as $key => $value) {
                    $visitTotals[$key] = ($visitTotals[$key] ?? 0) + $value;
                }
            }
        }

        $this->info(sprintf(
            'HHAeXchange sync — visits: %d synced, %d skipped, %d failed; caregivers: %d synced, %d skipped, %d failed.',
            $visitTotals['synced'],
            $visitTotals['skipped'],
            $visitTotals['failed'],
            $caregiverTotals['synced'],
            $caregiverTotals['skipped'],
            $caregiverTotals['failed'],
        ));

        return self::SUCCESS;
    }
}
