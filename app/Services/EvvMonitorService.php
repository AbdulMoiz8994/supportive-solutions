<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Visit/EVV Monitor Agent — watches care visits, auto-marks clean ones billable,
 * marks no-shows as Missed, flags stuck clock-ins for review, and pushes
 * problem visits to Tasks + Workflow Queues for humans.
 */
class EvvMonitorService
{
    public function __construct(
        protected VisitReportService $visits,
        protected TaskService $tasks,
        protected EvvWorkflowQueueService $workflowQueue,
    ) {}

    /**
     * @return array{missed: int, flagged: int, review_tasks: int, billable: int}
     */
    public function run(?int $organizationId = null): array
    {
        $missed = 0;
        $flagged = 0;
        $reviewTasks = 0;
        $billable = $this->autoMarkCleanBillable($organizationId);

        foreach ($this->candidateVisits($organizationId) as $schedule) {
            $status = $this->visits->resolveReportStatus($schedule);

            if ($status === VisitReportService::STATUS_SCHEDULED && $this->windowPassed($schedule)) {
                $this->visits->markMissed($schedule->organization_id, $schedule->id);
                $missed++;

                continue;
            }

            if ($status === VisitReportService::STATUS_NEEDS_REVIEW) {
                $metadata = $schedule->metadata ?? [];

                if (empty($metadata['evv_monitor_flagged_at'])) {
                    $metadata['evv_monitor_flagged_at'] = now()->toIso8601String();
                    $metadata['pending_review'] = true;
                    $schedule->update(['metadata' => $metadata]);
                    $flagged++;
                }

                if ($this->ensureReviewTask($schedule)) {
                    $reviewTasks++;
                }

                $this->workflowQueue->syncNeedsReview(
                    $schedule->fresh(['client', 'employee']) ?? $schedule,
                    $this->reviewReason($schedule),
                );
            }
        }

        return compact('missed', 'flagged', 'reviewTasks', 'billable');
    }

    /**
     * Sweep recent complete+clean visits the agent should auto-mark billable
     * (covers edge cases where clock-out didn't stamp billable).
     */
    private function autoMarkCleanBillable(?int $organizationId): int
    {
        $marked = 0;

        $schedules = Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->whereNotNull('actual_clock_in')
            ->whereNotNull('actual_clock_out')
            ->where(function ($q) {
                $q->whereDate('date', '>=', now()->subDays(7)->toDateString())
                    ->orWhere('start_at', '>=', now()->subDays(7));
            })
            ->whereNotIn('status', [
                Schedule::STATUS_MISSED,
                Schedule::STATUS_NO_SHOW,
                Schedule::STATUS_CANCELLED,
                'cancelled',
            ])
            ->with(['client', 'employee'])
            ->limit(500)
            ->get();

        foreach ($schedules as $schedule) {
            if (! empty(data_get($schedule->metadata, 'billable'))) {
                continue;
            }

            if (! $this->visits->isBillable($schedule)) {
                continue;
            }

            $this->visits->markBillableIfClean($schedule);
            $this->workflowQueue->resolveForVisit($schedule);
            $marked++;
        }

        return $marked;
    }

    private function reviewReason(Schedule $schedule): string
    {
        if ($schedule->actual_clock_in && ! $schedule->actual_clock_out) {
            return 'missing clock-out';
        }

        if ($this->visits->locationMatches($schedule) === false) {
            return 'location mismatch';
        }

        if ($this->visits->hasPendingTimeCorrection($schedule)) {
            return 'time correction awaiting approval';
        }

        return 'needs review before billing';
    }

    private function candidateVisits(?int $organizationId): Collection
    {
        // Include all recent non-terminal care visits; completed location/duration
        // failures are filtered in PHP via resolveReportStatus (evv_status alone
        // is not trustworthy — clock-out used to set it from duration only).
        return Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->with(['client', 'employee'])
            ->where(function ($q) {
                $q->whereDate('date', '>=', now()->subDays(7)->toDateString())
                    ->orWhere('start_at', '>=', now()->subDays(7));
            })
            ->whereNotIn('status', [
                Schedule::STATUS_MISSED,
                Schedule::STATUS_NO_SHOW,
                Schedule::STATUS_CANCELLED,
                'cancelled',
            ])
            ->limit(750)
            ->get()
            ->filter(function (Schedule $schedule) {
                $status = $this->visits->resolveReportStatus($schedule);

                return in_array($status, [
                    VisitReportService::STATUS_SCHEDULED,
                    VisitReportService::STATUS_IN_PROGRESS,
                    VisitReportService::STATUS_NEEDS_REVIEW,
                ], true);
            })
            ->values();
    }

    private function windowPassed(Schedule $schedule): bool
    {
        $end = $schedule->end_at ?? ($schedule->date && $schedule->end_time
            ? Carbon::parse($schedule->date->format('Y-m-d').' '.$schedule->end_time)
            : ($schedule->date?->copy()->endOfDay()));

        return $end && now()->greaterThan($end->copy()->addMinutes(30));
    }

    private function ensureReviewTask(Schedule $schedule): bool
    {
        $exists = Task::query()
            ->where('organization_id', $schedule->organization_id)
            ->where('related_type', Schedule::class)
            ->where('related_id', $schedule->id)
            ->where('source', Task::SOURCE_SYSTEM)
            ->where('title', 'like', 'Review EVV issue%')
            ->whereNotIn('status', [Task::STATUS_DONE])
            ->exists();

        if ($exists) {
            return false;
        }

        $clientName = $schedule->client
            ? trim($schedule->client->first_name.' '.$schedule->client->last_name)
            : 'client';

        $agent = app(AiAgentRegistryService::class)
            ->findBySlug($schedule->organization_id, 'evv');

        Task::create([
            'organization_id' => $schedule->organization_id,
            'title' => "Review EVV issue for {$clientName}",
            'description' => 'Visit/EVV Monitor Agent flagged this visit (missing clock-out, location, or time issue). Fix and approve before billing.',
            'status' => Task::STATUS_TODO,
            'priority' => Task::PRIORITY_HIGH,
            'due_date' => today(),
            'assignee_type' => $agent?->is_enabled ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
            'assignee_agent_id' => $agent?->is_enabled ? $agent->id : null,
            'client_id' => $schedule->client_id,
            'employee_id' => $schedule->employee_id,
            'related_type' => Schedule::class,
            'related_id' => $schedule->id,
            'source' => Task::SOURCE_SYSTEM,
        ]);

        return true;
    }
}
