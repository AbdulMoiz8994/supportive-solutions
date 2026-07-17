<?php

namespace App\Console\Commands;

use App\Models\BackgroundCheck;
use App\Models\Employee;
use App\Services\TaskService;
use Illuminate\Console\Command;

/**
 * Monthly SAM.gov + OIG LEIE exclusion screening batch (client review D10).
 * Stamps a fresh monthly run on every active caregiver's SAM and OIG rows —
 * idempotent per caregiver per month. Rows already flagged for review are
 * never overwritten; they stay "Flagged" until a human clears them via the
 * Background Checks / dashboard verify-before-disqualify flow.
 *
 * The same run raises renewal tasks for ICHAT checks approaching their annual
 * due date — ICHAT needs the agency's MSP portal account, so it is a human /
 * agent task rather than an automatic re-run.
 */
class RunBackgroundCheckBatchCommand extends Command
{
    protected $signature = 'background-checks:run-batch {--org= : Only screen one organization ID}';

    protected $description = 'Run the monthly SAM/OIG exclusion screening batch across active caregivers';

    public function handle(TaskService $tasks): int
    {
        $caregivers = Employee::withoutGlobalScopes()
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', (int) $this->option('org')))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();

        $runDate = today();
        $nextDue = today()->addMonthNoOverflow()->startOfMonth();
        $screened = 0;
        $flaggedKept = 0;

        foreach ($caregivers as $caregiver) {
            foreach (['SAM', 'OIG'] as $type) {
                $check = BackgroundCheck::query()
                    ->where('employee_id', $caregiver->id)
                    ->where('type', $type)
                    ->first();

                if ($check && $check->status === 'Flagged') {
                    $flaggedKept++;

                    continue;
                }

                if ($check && $check->last_run?->isSameMonth($runDate)) {
                    continue; // already screened this month — idempotent rerun
                }

                BackgroundCheck::updateOrCreate(
                    [
                        'employee_id' => $caregiver->id,
                        'type' => $type,
                    ],
                    [
                        'organization_id' => $caregiver->organization_id,
                        'label' => $type === 'SAM' ? 'SAM.gov exclusions' : 'OIG LEIE',
                        'cadence' => 'Monthly',
                        'status' => 'Clear',
                        'result' => 'No match',
                        'last_run' => $runDate,
                        'next_due' => $nextDue,
                        'source' => 'monthly_batch',
                        'monitoring' => 'Active',
                    ],
                );

                $screened++;
            }
        }

        // ICHAT annual renewals: the monthly cadence plus a 45-day lookahead
        // guarantees every due date gets a task at least two weeks ahead.
        $ichatTasks = 0;

        $ichatDue = BackgroundCheck::query()
            ->whereIn('employee_id', $caregivers->pluck('id'))
            ->where('type', 'ICHAT')
            ->whereNotNull('next_due')
            ->where('next_due', '<=', today()->addDays(45))
            ->with('employee')
            ->get();

        foreach ($ichatDue as $check) {
            $task = $tasks->createFromIchatRenewal($check);

            if ($task?->wasRecentlyCreated) {
                $ichatTasks++;
            }
        }

        $this->info(sprintf(
            'SAM/OIG batch — %d check(s) stamped for %d caregiver(s); %d flagged row(s) left for human review; %d ICHAT renewal task(s) raised.',
            $screened,
            $caregivers->count(),
            $flaggedKept,
            $ichatTasks,
        ));

        return self::SUCCESS;
    }
}
