<?php

namespace App\Jobs;

use App\Services\Billing\BillingCycleAutomationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Monthly billing cycle job (D2): scans clean visits + valid auths, generates
 * claims, and auto-submits when automation.auto_submit_billing is enabled.
 */
class GenerateMonthlyClaimsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public ?int $organizationId = null,
        public ?string $periodYm = null,
    ) {}

    public function handle(BillingCycleAutomationService $automation): void
    {
        $period = $this->periodYm
            ? Carbon::createFromFormat('Y-m', $this->periodYm)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $totals = $automation->runAllOrganizations($period, $this->organizationId);

        Log::info('GenerateMonthlyClaimsJob completed', [
            'organization_id' => $this->organizationId,
            'period' => $period->format('Y-m'),
            'totals' => $totals,
        ]);
    }
}
