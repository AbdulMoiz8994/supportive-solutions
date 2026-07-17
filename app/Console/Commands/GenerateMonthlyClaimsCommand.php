<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingCycleAutomationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * First-of-month billing automation (client review D2): builds/refreshes the
 * Billing & Claims Audit rows for every client with completed, clean visits
 * and a valid authorization in the billed period. Generation is idempotent —
 * existing claims are refreshed, never duplicated — and CP-01 gating still
 * holds claims that must not go out. When automation.auto_submit_billing is
 * enabled, eligible claims are submitted immediately after generation.
 *
 * The scheduler dispatches {@see \App\Jobs\GenerateMonthlyClaimsJob} directly;
 * this command is for manual / test runs.
 */
class GenerateMonthlyClaimsCommand extends Command
{
    protected $signature = 'billing:generate-claims
        {--period= : Billing period as YYYY-MM (defaults to the previous month)}
        {--org= : Only generate for one organization ID}';

    protected $description = 'Generate monthly billing claims from clean visits and valid auths for every organization';

    public function handle(BillingCycleAutomationService $automation): int
    {
        $period = $this->option('period')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('period'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $orgId = $this->option('org') ? (int) $this->option('org') : null;

        $totals = $automation->runAllOrganizations($period, $orgId);
        $this->outputTotals($period, $totals);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $totals
     */
    protected function outputTotals(Carbon $period, array $totals): void
    {
        $message = sprintf(
            'Billing run for %s — %d eligible, %d generated, %d refreshed, %d held (CP-01), %d skipped.',
            $period->format('M Y'),
            $totals['eligible'] ?? 0,
            $totals['generated'] ?? 0,
            $totals['refreshed'] ?? 0,
            $totals['cp01_held'] ?? 0,
            $totals['skipped'] ?? 0,
        );

        if (($totals['submitted'] ?? 0) > 0 || ($totals['failed'] ?? 0) > 0) {
            $message .= sprintf(
                ' Auto-submit: %d submitted (%d Availity, %d DHS), %d failed.',
                $totals['submitted'] ?? 0,
                $totals['availity'] ?? 0,
                $totals['sigma_dhs'] ?? 0,
                $totals['failed'] ?? 0,
            );
        }

        $this->info($message);
    }
}
