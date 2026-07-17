<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Client;
use App\Models\Intake;
use App\Models\Organization;
use App\Models\Schedule;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        protected OrganizationMetricsService $metricsService
    ) {}

    public function build(?int $organizationId = null): array
    {
        $orgId = $this->metricsService->resolveOrganizationId($organizationId);

        return [
            'statCards' => $this->metricsService->getStatCards($orgId),
            'headerMeta' => $this->buildHeaderMeta(),
            'revenueOverview' => $this->buildRevenueOverview($orgId),
            'monthlyChart' => $this->buildMonthlyChart($orgId),
            'billingDonut' => $this->buildBillingDonut($orgId),
            'recentActivities' => $this->buildRecentActivities($orgId),
            'organizations' => $this->buildOrganizationRows($orgId),
        ];
    }

    protected function buildHeaderMeta(): array
    {
        return [
            'refreshed_at' => now()->format('D, M d Y'),
            'timezone' => 'UTC'.now()->format('P'),
        ];
    }

    protected function buildRevenueOverview(?int $orgId): array
    {
        if (! $orgId) {
            return [
                'total' => '$0',
                'change' => '0%',
                'change_positive' => true,
            ];
        }

        $currentMonth = $this->monthBillingTotal($orgId, now());
        $lastMonth = $this->monthBillingTotal($orgId, now()->subMonth());
        $change = $this->formatPercentChange($currentMonth, $lastMonth);

        return [
            'total' => $this->formatCurrencyDetailed($currentMonth),
            'change' => $change,
            'change_positive' => ! str_starts_with($change, '-'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildMonthlyChart(?int $orgId): array
    {
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $authorized = $orgId ? $this->monthBillingTotal($orgId, $date) : 0;
            $collected = $orgId ? $this->monthCollectedTotal($orgId, $date) : 0;

            $months[] = [
                'label' => $date->format('M'),
                'authorized' => $authorized,
                'collected' => $collected,
            ];
        }

        $max = max(1, ...array_map(fn ($month) => max($month['authorized'], $month['collected']), $months));

        return array_map(function (array $month) use ($max) {
            $month['h1'] = $max > 0 ? max(5, (int) round(($month['authorized'] / $max) * 100)) : 5;
            $month['h2'] = $max > 0 ? max(3, (int) round(($month['collected'] / $max) * 100)) : 3;

            return $month;
        }, $months);
    }

    protected function buildBillingDonut(?int $orgId): array
    {
        if (! $orgId) {
            return $this->emptyBillingDonut();
        }

        $authorized = $this->monthBillingTotal($orgId, now());
        $collected = $this->monthCollectedTotal($orgId, now());
        $remaining = max(0, $authorized - $collected);

        $authorizedPct = $authorized > 0 ? 100 : 0;
        $collectedPct = $authorized > 0 ? round(($collected / $authorized) * 100) : 0;
        $remainingPct = $authorized > 0 ? round(($remaining / $authorized) * 100) : 0;

        $circumference = 251;
        $collectedOffset = (int) round($circumference - ($circumference * ($collectedPct / 100)));
        $remainingOffset = (int) round($circumference - ($circumference * (($collectedPct + $remainingPct) / 100)));

        return [
            'total' => $this->formatCurrencyShort($authorized),
            'rows' => [
                [
                    'label' => 'Authorized Amount',
                    'color' => 'bg-[#2563eb]',
                    'val' => $this->formatCurrencyShort($authorized),
                    'pct' => $authorizedPct.'%',
                    'pctBg' => 'bg-[#d1fae5]',
                    'pctText' => 'text-[#059669]',
                ],
                [
                    'label' => 'Collected Revenue',
                    'color' => 'bg-[#f59e0b]',
                    'val' => $this->formatCurrencyShort($collected),
                    'pct' => $collectedPct.'%',
                    'pctBg' => 'bg-[#f1f5f9]',
                    'pctText' => 'text-[#64748b]',
                ],
                [
                    'label' => 'Remaining / Pending',
                    'color' => 'bg-[#e2e8f0]',
                    'val' => $this->formatCurrencyShort($remaining),
                    'desc' => '(remaining from target)',
                    'pct' => $remainingPct.'%',
                    'pctBg' => 'bg-[#fee2e2]',
                    'pctText' => 'text-[#dc2626]',
                ],
            ],
            'collected_offset' => $collectedOffset,
            'remaining_offset' => $remainingOffset,
        ];
    }

    protected function emptyBillingDonut(): array
    {
        return [
            'total' => '$0',
            'rows' => [
                ['label' => 'Authorized Amount', 'color' => 'bg-[#2563eb]', 'val' => '$0', 'pct' => '0%', 'pctBg' => 'bg-[#d1fae5]', 'pctText' => 'text-[#059669]'],
                ['label' => 'Collected Revenue', 'color' => 'bg-[#f59e0b]', 'val' => '$0', 'pct' => '0%', 'pctBg' => 'bg-[#f1f5f9]', 'pctText' => 'text-[#64748b]'],
                ['label' => 'Remaining / Pending', 'color' => 'bg-[#e2e8f0]', 'val' => '$0', 'desc' => '(remaining from target)', 'pct' => '0%', 'pctBg' => 'bg-[#fee2e2]', 'pctText' => 'text-[#dc2626]'],
            ],
            'collected_offset' => 251,
            'remaining_offset' => 251,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRecentActivities(?int $orgId): array
    {
        if (! $orgId) {
            return [];
        }

        return ActivityLog::query()
            ->where('organization_id', $orgId)
            ->with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (ActivityLog $log) {
                return [
                    'time' => $log->created_at?->diffForHumans() ?? 'Recently',
                    'title' => $log->action.':',
                    'desc' => $log->description ?? 'No additional details recorded.',
                    'user' => $log->user?->name ? 'User: '.$log->user->name : 'System',
                    'icon' => $this->activityIcon($log->action),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildOrganizationRows(?int $orgId): array
    {
        $query = Organization::query()->withCount('clients');

        if ($orgId) {
            $query->where('id', $orgId);
        }

        return $query->orderBy('name')->get()->map(function (Organization $organization) {
            $address = $organization->address ?? '—';
            $state = $this->extractState($address);

            return [
                'id' => $organization->id,
                'name' => $organization->name,
                'clients_count' => number_format($organization->clients_count),
                'billing_cycle' => 'Monthly',
                'plan' => 'Enterprise',
                'contract_renewal' => $organization->updated_at?->format('m/d/Y') ?? '—',
                'address' => $address,
                'state' => $state,
                'status' => $organization->status ?? 'Active',
                'email' => $this->extractEmail($organization->contact_info) ?? '—',
                'avatar_name' => $organization->name,
            ];
        })->all();
    }

    protected function monthBillingTotal(int $orgId, Carbon $date): float
    {
        return (float) Billing::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->sum('total_amount');
    }

    protected function monthCollectedTotal(int $orgId, Carbon $date): float
    {
        return (float) Billing::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->where('status', 'Paid')
            ->sum('total_amount');
    }

    protected function formatPercentChange(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign.number_format($change, 0).'%';
    }

    protected function formatCurrencyDetailed(float $amount): string
    {
        if ($amount >= 1_000) {
            return '$'.number_format($amount / 1_000, 1).'k';
        }

        return '$'.number_format($amount, 0);
    }

    protected function formatCurrencyShort(float $amount): string
    {
        if ($amount >= 1_000) {
            return '$'.number_format($amount / 1_000, 0).'k';
        }

        return '$'.number_format($amount, 0);
    }

    protected function activityIcon(string $action): string
    {
        return str_contains(strtolower($action), 'login') ? 'bg-[#1e293b]' : 'bg-[#2563eb]';
    }

    protected function extractState(string $address): string
    {
        if (preg_match('/\b([A-Z]{2})\b/', $address, $matches)) {
            return $matches[1];
        }

        return '—';
    }

    protected function extractEmail(?string $contactInfo): ?string
    {
        if (! $contactInfo) {
            return null;
        }

        if (filter_var($contactInfo, FILTER_VALIDATE_EMAIL)) {
            return $contactInfo;
        }

        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $contactInfo, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
