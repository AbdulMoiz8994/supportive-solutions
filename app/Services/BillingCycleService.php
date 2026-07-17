<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class BillingCycleService
{
    public function __construct(
        protected GlobalSettingsService $settingsService
    ) {}

    public function cycle(): string
    {
        return $this->settingsService->defaultBillingCycle();
    }

    public function periodStart(?string $cycle = null): Carbon
    {
        $cycle = $cycle ?? $this->cycle();

        return match ($cycle) {
            'weekly' => now()->startOfWeek(),
            'biweekly' => now()->subDays(13)->startOfDay(),
            default => now()->startOfMonth(),
        };
    }

    public function periodEnd(?string $cycle = null): Carbon
    {
        $cycle = $cycle ?? $this->cycle();

        return match ($cycle) {
            'weekly' => now()->endOfWeek(),
            'biweekly' => now()->endOfDay(),
            default => now()->endOfMonth(),
        };
    }

    public function invoicePrefix(?string $cycle = null): string
    {
        $cycle = $cycle ?? $this->cycle();

        return match ($cycle) {
            'weekly' => 'INV-W',
            'biweekly' => 'INV-B',
            default => 'INV-M',
        };
    }

    /**
     * @param  Collection<int, \App\Models\Schedule>  $schedules
     */
    public function filterSchedulesForCycle(Collection $schedules, ?string $cycle = null): Collection
    {
        $start = $this->periodStart($cycle);
        $end = $this->periodEnd($cycle);

        return $schedules->filter(function ($schedule) use ($start, $end) {
            $date = Carbon::parse($schedule->date);

            return $date->betweenIncluded($start->copy()->startOfDay(), $end->copy()->endOfDay());
        });
    }
}
