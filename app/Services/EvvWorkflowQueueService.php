<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\WorkflowQueueItem;

/**
 * Pushes problem EVV visits to Workflow Queues for human Fix/Approve,
 * matching the Visit/EVV Monitor Agent "writes to Workflow Queues" contract.
 */
class EvvWorkflowQueueService
{
    public function syncNeedsReview(Schedule $schedule, string $reason = 'EVV issue'): void
    {
        $clientName = $this->clientName($schedule);
        $caregiverName = $this->caregiverName($schedule);

        WorkflowQueueItem::updateOrCreate(
            [
                'organization_id' => $schedule->organization_id,
                'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                'slug' => $this->reviewSlug($schedule),
            ],
            [
                'status' => WorkflowQueueItem::STATUS_PENDING,
                'subject_type' => Schedule::class,
                'subject_id' => $schedule->id,
                'sla_due_at' => now()->addHours((int) config('workflow_queues.sla_hours', 24)),
                'meta' => [
                    'title' => "Fix/Approve EVV — {$clientName}",
                    'subtitle' => "{$caregiverName} · {$reason} · not billable until fixed",
                    'due_label' => 'Fix within 24 hrs',
                    'due_tone' => 'urgent',
                    'assignee' => 'Visit/EVV Monitor Agent',
                    'review_url' => route('visit-reports', [
                        'report_status' => VisitReportService::STATUS_NEEDS_REVIEW,
                        'date_preset' => 'this_week',
                    ]),
                    'schedule_id' => $schedule->id,
                ],
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        );
    }

    public function syncMissedVisit(Schedule $schedule): void
    {
        $clientName = $this->clientName($schedule);
        $caregiverName = $this->caregiverName($schedule);

        WorkflowQueueItem::updateOrCreate(
            [
                'organization_id' => $schedule->organization_id,
                'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                'slug' => $this->missedSlug($schedule),
            ],
            [
                'status' => WorkflowQueueItem::STATUS_PENDING,
                'subject_type' => Schedule::class,
                'subject_id' => $schedule->id,
                'sla_due_at' => now()->addHours((int) config('workflow_queues.sla_hours', 24)),
                'meta' => [
                    'title' => "Reschedule missed visit — {$clientName}",
                    'subtitle' => "{$caregiverName} · no-show · follow up to reschedule",
                    'due_label' => 'Reschedule within 24 hrs',
                    'due_tone' => 'urgent',
                    'assignee' => 'Communications VA',
                    'review_url' => route('visit-reports', [
                        'report_status' => VisitReportService::STATUS_MISSED,
                        'date_preset' => 'this_week',
                    ]),
                    'schedule_id' => $schedule->id,
                ],
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        );
    }

    public function resolveForVisit(Schedule $schedule): void
    {
        WorkflowQueueItem::query()
            ->where('organization_id', $schedule->organization_id)
            ->where('queue_type', WorkflowQueueItem::TYPE_HUMAN_TASK)
            ->whereIn('slug', [$this->reviewSlug($schedule), $this->missedSlug($schedule)])
            ->where('status', WorkflowQueueItem::STATUS_PENDING)
            ->update([
                'status' => WorkflowQueueItem::STATUS_COMPLETED,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
            ]);
    }

    protected function reviewSlug(Schedule $schedule): string
    {
        return 'evv-review-'.$schedule->id;
    }

    protected function missedSlug(Schedule $schedule): string
    {
        return 'evv-missed-'.$schedule->id;
    }

    protected function clientName(Schedule $schedule): string
    {
        return $schedule->client
            ? trim($schedule->client->first_name.' '.$schedule->client->last_name)
            : 'client';
    }

    protected function caregiverName(Schedule $schedule): string
    {
        return $schedule->employee
            ? trim($schedule->employee->first_name.' '.$schedule->employee->last_name)
            : 'caregiver';
    }
}
