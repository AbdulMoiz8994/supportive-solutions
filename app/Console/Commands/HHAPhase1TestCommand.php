<?php

namespace App\Console\Commands;

use App\Services\HHA\HHAPhase1TestService;
use Illuminate\Console\Command;

class HHAPhase1TestCommand extends Command
{
    protected $signature = 'hha:phase1-test
        {scenario? : Scenario id (1-001 … 10-001). Omit to list scenarios.}
        {--employee-id= : Employee/caregiver id for caregiver scenarios}
        {--schedule-id= : Schedule id for visit scenarios}
        {--transaction-id= : Transaction id for 4-002 / 5-002}
        {--evvmsid= : EVVMSID for 7-001 / 8-001}
        {--reason-code=1 : Missed/edit reason code from MI import specs}
        {--action-code=1 : Missed/edit action code from MI import specs}';

    protected $description = 'Run HHAeXchange Third-Party EVV API Phase 1 test scenarios (Tests 1-001 through 10-001)';

    public function handle(HHAPhase1TestService $phase1): int
    {
        $scenario = $this->argument('scenario');

        if (! $scenario) {
            $this->info('HHAeXchange Phase 1 scenarios:');
            foreach ($phase1->supportedScenarios() as $id) {
                $this->line('  '.$id);
            }
            $this->newLine();
            $this->line('Example: php artisan hha:phase1-test 1-001');
            $this->line('Postman auth: POST {token_url} as x-www-form-urlencoded with grant_type=client_credentials, client_id, client_secret, scope=write:aggregator');

            return self::SUCCESS;
        }

        try {
            $result = $phase1->run((string) $scenario, [
                'employee_id' => $this->option('employee-id'),
                'schedule_id' => $this->option('schedule-id'),
                'transaction_id' => $this->option('transaction-id'),
                'evvmsid' => $this->option('evvmsid'),
                'reason_code' => $this->option('reason-code'),
                'action_code' => $this->option('action-code'),
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['success']) {
            $this->info('['.$result['scenario'].'] '.$result['message']);
        } else {
            $this->error('['.$result['scenario'].'] '.$result['message']);
        }

        if (! empty($result['details'])) {
            $this->line(json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
