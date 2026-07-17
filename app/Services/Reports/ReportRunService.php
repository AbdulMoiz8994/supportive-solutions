<?php

namespace App\Services\Reports;

use App\Models\ReportRun;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportRunService
{
    public function record(
        string $reportSlug,
        ?int $organizationId,
        ?int $userId,
        Carbon $period,
        string $format = 'csv',
        int $rowCount = 0,
        ?int $scheduleId = null,
        ?int $customReportId = null,
    ): ReportRun {
        return ReportRun::create([
            'organization_id' => $organizationId,
            'report_slug' => $reportSlug,
            'custom_report_id' => $customReportId,
            'report_schedule_id' => $scheduleId,
            'user_id' => $userId,
            'period' => $period->format('Y-m'),
            'format' => $format,
            'status' => ReportRun::STATUS_COMPLETED,
            'row_count' => $rowCount,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function lastRunLabels(?int $organizationId, ?string $category = null): array
    {
        $query = ReportRun::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', ReportRun::STATUS_COMPLETED)
            ->orderByDesc('completed_at');

        if ($category) {
            $slugs = collect(config('reports.reports', []))
                ->filter(fn ($r) => ($r['category'] ?? '') === $category)
                ->keys();
            $query->whereIn('report_slug', $slugs);
        }

        return $query->get()
            ->groupBy('report_slug')
            ->map(fn (Collection $runs) => optional($runs->first()->completed_at)->format('M j, Y') ?? '—')
            ->all();
    }

    public function lastRunFor(string $reportSlug, ?int $organizationId): ?Carbon
    {
        $run = ReportRun::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('report_slug', $reportSlug)
            ->where('status', ReportRun::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        return $run?->completed_at;
    }
}
