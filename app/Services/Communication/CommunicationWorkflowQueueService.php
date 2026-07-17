<?php

namespace App\Services\Communication;

use App\Models\Communication;
use App\Models\WorkflowQueueItem;

/**
 * Routes inbound communications that need a human reply into the Workflow Queue.
 */
class CommunicationWorkflowQueueService
{
    public function syncInboundItem(Communication $communication): void
    {
        if ($communication->direction !== Communication::DIRECTION_INBOUND) {
            return;
        }

        $metadata = $communication->metadata ?? [];
        $handledBy = $metadata['handled_by'] ?? null;

        if ($handledBy !== 'needs_review') {
            return;
        }

        $channel = ucfirst($communication->channel);
        $party = (string) ($metadata['party_name'] ?? 'Unknown sender');
        $summary = (string) ($metadata['ai_summary'] ?? $communication->subject ?? 'Inbound message needs a reply');

        WorkflowQueueItem::updateOrCreate(
            [
                'organization_id' => $communication->organization_id,
                'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                'slug' => $this->slugFor($communication),
            ],
            [
                'status' => WorkflowQueueItem::STATUS_PENDING,
                'subject_type' => Communication::class,
                'subject_id' => $communication->id,
                'sla_due_at' => ($communication->sent_at ?? $communication->created_at)?->copy()->addHours(24) ?? now()->addHours(24),
                'meta' => [
                    'title' => "Reply needed — {$party}",
                    'subtitle' => "{$channel} · ".$summary,
                    'due_label' => 'Reply within 24 hrs',
                    'due_tone' => 'urgent',
                    'assignee' => 'Communications VA',
                    'review_url' => route('communications.show', $communication),
                    'communication_id' => $communication->id,
                ],
            ],
        );
    }

    public function resolveItem(Communication $communication): void
    {
        WorkflowQueueItem::query()
            ->where('organization_id', $communication->organization_id)
            ->where('queue_type', WorkflowQueueItem::TYPE_HUMAN_TASK)
            ->where('slug', $this->slugFor($communication))
            ->where('status', WorkflowQueueItem::STATUS_PENDING)
            ->update([
                'status' => WorkflowQueueItem::STATUS_COMPLETED,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
            ]);
    }

    protected function slugFor(Communication $communication): string
    {
        return 'comm-inbound-'.$communication->id;
    }
}
