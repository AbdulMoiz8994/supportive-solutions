<?php

namespace App\Console\Commands;

use App\Services\EvvMonitorService;
use App\Services\VisitReportService;
use App\Models\Schedule;
use Illuminate\Console\Command;

/**
 * Extends EVV monitoring: for stuck clock-ins, suggest a clock-out at the
 * scheduled end (human still must approve via Visit Reports Fix/Approve).
 */
class SuggestEvvTimeFixesCommand extends Command
{
    protected $signature = 'evv:suggest-fixes {--organization= : Limit to one organization}';

    protected $description = 'Suggest time corrections for EVV visits that need review';

    public function handle(VisitReportService $visits): int
    {
        $orgId = $this->option('organization') ? (int) $this->option('organization') : null;
        $count = 0;

        $schedules = Schedule::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->whereNotNull('actual_clock_in')
            ->whereNull('actual_clock_out')
            ->whereNotIn('status', [Schedule::STATUS_MISSED, Schedule::STATUS_CANCELLED])
            ->limit(200)
            ->get();

        foreach ($schedules as $schedule) {
            if ($visits->resolveReportStatus($schedule) !== VisitReportService::STATUS_NEEDS_REVIEW) {
                continue;
            }

            $pending = collect(data_get($schedule->metadata, 'time_corrections', []))
                ->contains(fn ($c) => empty($c['approved']));

            if ($pending) {
                continue;
            }

            $proposed = $schedule->end_at
                ?? ($schedule->date && $schedule->end_time
                    ? $schedule->date->format('Y-m-d').' '.$schedule->end_time
                    : now()->toDateTimeString());

            $metadata = $schedule->metadata ?? [];
            $corrections = $metadata['time_corrections'] ?? [];
            $corrections[] = [
                'field' => 'actual_clock_out',
                'original' => null,
                'proposed' => \Carbon\Carbon::parse($proposed)->toIso8601String(),
                'reason' => 'Visit/EVV Monitor Agent suggested clock-out at scheduled end (forgotten clock-out).',
                'by_user_id' => null,
                'by_user_name' => 'Visit/EVV Monitor Agent',
                'created_at' => now()->toIso8601String(),
                'approved' => false,
                'suggested_by_agent' => true,
            ];
            $metadata['time_corrections'] = $corrections;
            $metadata['pending_review'] = true;
            $schedule->update(['metadata' => $metadata]);
            app(\App\Services\EvvWorkflowQueueService::class)->syncNeedsReview(
                $schedule->fresh(['client', 'employee']) ?? $schedule,
                'agent suggested clock-out',
            );
            $count++;
        }

        $this->info("Suggested {$count} time correction(s).");

        return self::SUCCESS;
    }
}
