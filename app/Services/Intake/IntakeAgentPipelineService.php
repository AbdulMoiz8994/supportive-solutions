<?php

namespace App\Services\Intake;

use App\Models\AiAgent;
use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Intake;
use App\Models\WorkflowQueueItem;
use App\Services\AiAgentRegistryService;

/**
 * Scan-first intake (D1): after profile creation the Intake Agent verifies
 * eligibility, builds the client chart, queues activation, and hands off
 * compliance / PA / background-check follow-ups.
 */
class IntakeAgentPipelineService
{
    public function __construct(
        protected AiAgentRegistryService $agentRegistry,
        protected IntakeConversionService $conversion,
    ) {}

    public function submit(Intake $intake): void
    {
        $agent = $this->intakeAgent($intake->organization_id);

        if (! $agent) {
            return;
        }

        $modes = collect($this->agentRegistry->actionDefinitions($agent))->keyBy('key');

        $this->runVerifyEligibility(
            $intake,
            (string) ($modes->get('verify_eligibility')['mode'] ?? 'queue'),
        );

        $intake->refresh();

        if ($intake->status === 'Ineligible') {
            return;
        }

        $this->runBuildChart(
            $intake,
            (string) ($modes->get('build_chart')['mode'] ?? 'auto'),
            (string) ($modes->get('activate_client')['mode'] ?? 'queue'),
        );
    }

    /**
     * Queue post-conversion agent follow-ups (compliance docs, PA submit, bg check).
     */
    public function handOffAfterConversion(Intake $intake, Client $client, ?CareDetail $careDetail = null): void
    {
        if (! $this->intakeAgent($intake->organization_id)) {
            return;
        }

        $name = trim($client->first_name.' '.$client->last_name);

        WorkflowQueueItem::updateOrCreate(
            [
                'organization_id' => $intake->organization_id,
                'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                'slug' => 'intake-compliance-docs-'.$client->id,
            ],
            [
                'status' => WorkflowQueueItem::STATUS_PENDING,
                'subject_type' => Client::class,
                'subject_id' => $client->id,
                'sla_due_at' => now()->addHours(48),
                'meta' => [
                    'title' => "Request compliance documents — {$name}",
                    'subtitle' => 'Intake Agent · ID, assessment, and program forms for the new chart',
                    'due_label' => 'Request within 48 hrs',
                    'due_tone' => 'soon',
                    'assignee' => 'Intake Agent',
                    'review_url' => route('clients.show', $client->id),
                    'client_id' => $client->id,
                ],
            ],
        );

        if ($intake->isManagedCareTrack()) {
            WorkflowQueueItem::updateOrCreate(
                [
                    'organization_id' => $intake->organization_id,
                    'queue_type' => WorkflowQueueItem::TYPE_APPROVAL,
                    'slug' => 'intake-pa-submit-'.($careDetail?->id ?? $client->id),
                ],
                [
                    'status' => WorkflowQueueItem::STATUS_PENDING,
                    'subject_type' => CareDetail::class,
                    'subject_id' => $careDetail?->id,
                    'sla_due_at' => now()->addHours(24),
                    'meta' => [
                        'title' => "Submit Prior Authorization — {$name}",
                        'subtitle' => strtoupper($intake->program_track ?? 'mich').' · '
                            .($intake->pa_units ? $intake->pa_units.' units requested' : 'PA units pending'),
                        'due_label' => 'Submit within 24 hrs',
                        'due_tone' => 'urgent',
                        'assignee' => 'Authorizations Agent',
                        'review_url' => route('clients.show', ['id' => $client->id, 'tab' => 'authorization']),
                        'client_id' => $client->id,
                        'care_detail_id' => $careDetail?->id,
                    ],
                ],
            );
        }

        if ($intake->assigned_employee_id) {
            $caregiver = Employee::withoutGlobalScopes()->find($intake->assigned_employee_id);
            $caregiverName = $caregiver
                ? trim($caregiver->first_name.' '.$caregiver->last_name)
                : 'Assigned caregiver';

            WorkflowQueueItem::updateOrCreate(
                [
                    'organization_id' => $intake->organization_id,
                    'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                    'slug' => 'intake-bg-check-'.$intake->assigned_employee_id.'-'.$client->id,
                ],
                [
                    'status' => WorkflowQueueItem::STATUS_PENDING,
                    'subject_type' => Employee::class,
                    'subject_id' => $intake->assigned_employee_id,
                    'sla_due_at' => now()->addHours(24),
                    'meta' => [
                        'title' => "Verify background check — {$caregiverName}",
                        'subtitle' => "Assigned to {$name} · confirm SAM/OIG/ICHAT before first visit",
                        'due_label' => 'Verify within 24 hrs',
                        'due_tone' => 'urgent',
                        'assignee' => 'Background Checks Agent',
                        'review_url' => route('caregivers.show', ['id' => $intake->assigned_employee_id, 'tab' => 'checks']),
                        'employee_id' => $intake->assigned_employee_id,
                        'client_id' => $client->id,
                    ],
                ],
            );
        }
    }

    protected function intakeAgent(?int $organizationId): ?AiAgent
    {
        if (! $organizationId) {
            return null;
        }

        $this->agentRegistry->ensureCatalog($organizationId);
        $agent = $this->agentRegistry->findBySlug($organizationId, 'intake');

        return ($agent && $agent->isActive()) ? $agent : null;
    }

    protected function runVerifyEligibility(Intake $intake, string $mode): void
    {
        if ($mode === 'monitor') {
            return;
        }

        $needsReview = blank($intake->eligibility_status)
            || $intake->eligibility_status === Intake::ELIGIBILITY_NEEDS_VERIFICATION;

        if ($mode === 'auto' && $intake->eligibility_status === Intake::ELIGIBILITY_INELIGIBLE) {
            $intake->update([
                'status' => 'Ineligible',
                'notes' => ($intake->notes ? $intake->notes."\n" : '')
                    .'❌ Intake Agent marked ineligible on '.now()->format('M d, Y'),
            ]);

            return;
        }

        if ($mode === 'queue' && $needsReview) {
            $name = trim($intake->first_name.' '.$intake->last_name);

            WorkflowQueueItem::updateOrCreate(
                [
                    'organization_id' => $intake->organization_id,
                    'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                    'slug' => 'intake-eligibility-'.$intake->id,
                ],
                [
                    'status' => WorkflowQueueItem::STATUS_PENDING,
                    'subject_type' => Intake::class,
                    'subject_id' => $intake->id,
                    'sla_due_at' => now()->addHours(24),
                    'meta' => [
                        'title' => "Verify eligibility — {$name}",
                        'subtitle' => $intake->eligibility_note ?: 'Confirm Medicaid ID and payer coverage before chart build.',
                        'due_label' => 'Review within 24 hrs',
                        'due_tone' => 'urgent',
                        'assignee' => 'Intake Agent',
                        'review_url' => route('intakes.show', $intake->id),
                        'intake_id' => $intake->id,
                    ],
                ],
            );
        }
    }

    protected function runBuildChart(Intake $intake, string $mode, string $activateMode): void
    {
        if ($intake->converted_client_id || $intake->status === 'Ineligible') {
            return;
        }

        $pendingEligibility = WorkflowQueueItem::query()
            ->where('organization_id', $intake->organization_id)
            ->where('slug', 'intake-eligibility-'.$intake->id)
            ->where('status', WorkflowQueueItem::STATUS_PENDING)
            ->exists();

        if ($pendingEligibility) {
            return;
        }

        if ($mode === 'queue') {
            $name = trim($intake->first_name.' '.$intake->last_name);

            WorkflowQueueItem::updateOrCreate(
                [
                    'organization_id' => $intake->organization_id,
                    'queue_type' => WorkflowQueueItem::TYPE_HUMAN_TASK,
                    'slug' => 'intake-chart-'.$intake->id,
                ],
                [
                    'status' => WorkflowQueueItem::STATUS_PENDING,
                    'subject_type' => Intake::class,
                    'subject_id' => $intake->id,
                    'sla_due_at' => now()->addHours(24),
                    'meta' => [
                        'title' => "Build client chart — {$name}",
                        'subtitle' => 'Intake profile ready — convert to client and carry program forward.',
                        'due_label' => 'Convert within 24 hrs',
                        'due_tone' => 'soon',
                        'assignee' => 'Intake Agent',
                        'review_url' => route('intakes.show', $intake->id),
                        'intake_id' => $intake->id,
                    ],
                ],
            );

            return;
        }

        if ($mode === 'auto') {
            $result = $this->conversion->convert($intake, activateImmediately: $activateMode === 'auto');
            $this->handOffAfterConversion($result['intake'], $result['client'], $result['care_detail']);
        }
    }
}
