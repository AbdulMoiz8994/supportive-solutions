<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Intake;
use Carbon\Carbon;

class OrganizationMetricsService
{
    public function resolveOrganizationId(?int $organizationId = null): ?int
    {
        return $organizationId ?? auth()->user()?->organization_id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStatCards(?int $organizationId = null): array
    {
        $orgId = $this->resolveOrganizationId($organizationId);

        if (! $orgId) {
            return $this->emptyStatCards();
        }

        $now = now();
        $lastMonth = now()->subMonth();

        $activeClients = $this->countActiveClients($orgId);
        $activeClientsLast = $this->countActiveClients($orgId, $lastMonth);

        $activeIntakes = $this->countActiveIntakes($orgId);
        $activeIntakesLast = $this->countActiveIntakes($orgId, $lastMonth);

        $totalBilling = $this->sumBilling($orgId);
        $totalBillingLast = $this->sumBilling($orgId, $lastMonth);

        $activeEmployees = $this->countActiveEmployees($orgId);
        $activeEmployeesLast = $this->countActiveEmployees($orgId, $lastMonth);

        // Same source of truth as the dashboard queue and sidebar badge (A3).
        $pendingApprovals = app(WorkflowQueueService::class)->approvalCount($orgId);

        $employeeChange = $this->percentChange($activeEmployees, $activeEmployeesLast);

        return [
            [
                'title' => 'Active Clients',
                'value' => $this->formatNumber($activeClients),
                'change' => $this->percentChange($activeClients, $activeClientsLast),
                'icon' => 'users',
            ],
            [
                'title' => 'Active Intakes',
                'value' => $this->formatNumber($activeIntakes),
                'change' => $this->percentChange($activeIntakes, $activeIntakesLast),
                'icon' => 'file-text',
            ],
            [
                'title' => 'Total Billing',
                'value' => $this->formatCurrency($totalBilling),
                'change' => $this->percentChange($totalBilling, $totalBillingLast),
                'icon' => 'dollar-sign',
            ],
            [
                'title' => 'Employees Active',
                'value' => $this->formatNumber($activeEmployees),
                'status' => abs((float) str_replace(['%', '+'], '', $employeeChange)) < 0.5 ? 'Stable' : $employeeChange,
                'icon' => 'user-check',
            ],
            [
                'title' => 'Pending Approvals',
                'value' => $this->formatNumber($pendingApprovals),
                'change' => 'live',
                'icon' => 'check-circle',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function emptyStatCards(): array
    {
        return [
            ['title' => 'Active Clients', 'value' => '0', 'change' => '0%', 'icon' => 'users'],
            ['title' => 'Active Intakes', 'value' => '0', 'change' => '0%', 'icon' => 'file-text'],
            ['title' => 'Total Billing', 'value' => '$0', 'change' => '0%', 'icon' => 'dollar-sign'],
            ['title' => 'Employees Active', 'value' => '0', 'status' => 'Stable', 'icon' => 'user-check'],
            ['title' => 'Pending Approvals', 'value' => '0', 'change' => '0%', 'icon' => 'check-circle'],
        ];
    }

    protected function countActiveClients(int $orgId, ?Carbon $before = null): int
    {
        $query = Client::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', 'Active');

        if ($before) {
            $query->where('created_at', '<=', $before->copy()->endOfMonth());
        }

        return $query->count();
    }

    protected function countActiveIntakes(int $orgId, ?Carbon $before = null): int
    {
        $query = Intake::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereNull('converted_client_id')
            ->where('status', '!=', 'Converted');

        if ($before) {
            $query->where('created_at', '<=', $before->copy()->endOfMonth());
        }

        return $query->count();
    }

    protected function sumBilling(int $orgId, ?Carbon $before = null): float
    {
        $query = Billing::withoutGlobalScopes()
            ->where('organization_id', $orgId);

        if ($before) {
            $query->where('created_at', '<=', $before->copy()->endOfMonth());
        }

        return (float) $query->sum('total_amount');
    }

    protected function countActiveEmployees(int $orgId, ?Carbon $before = null): int
    {
        $query = Employee::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', 'Active');

        if ($before) {
            $query->where('created_at', '<=', $before->copy()->endOfMonth());
        }

        return $query->count();
    }

    protected function percentChange(float|int $current, float|int $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign.number_format($change, 1).'%';
    }

    protected function formatNumber(int $value): string
    {
        return number_format($value);
    }

    protected function formatCurrency(float $amount): string
    {
        if ($amount >= 1_000_000) {
            return '$'.number_format($amount / 1_000_000, 1).'M';
        }

        if ($amount >= 1_000) {
            return '$'.number_format($amount / 1_000, $amount >= 10_000 ? 0 : 1).'k';
        }

        return '$'.number_format($amount, 0);
    }
}
