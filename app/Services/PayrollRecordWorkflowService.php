<?php

namespace App\Services;

use App\Models\ComplianceForm;
use App\Models\PayRecord;
use Carbon\Carbon;

class PayrollRecordWorkflowService
{
    public function __construct(
        protected PayrollCalculationService $calculationService,
        protected PayrollGraceWindowService $graceWindowService,
        protected PayrollHoursResolver $hoursResolver,
        protected PayrollEligibilityService $eligibilityService
    ) {}

    public function refreshRecord(PayRecord $record, ?Carbon $asOf = null): PayRecord
    {
        $record->loadMissing(['employee.assignments', 'client', 'complianceForm']);

        if (! $record->period_key) {
            $record->period_key = $this->hoursResolver->periodKeyFromLabel($record->period);
        }

        if (! $record->caregiver_type) {
            $record->caregiver_type = $this->eligibilityService->resolveCaregiverType($record);
        }

        // P5 — supplemental/reversal records carry their own captured amounts and
        // status; never recompute them from the compliance form / grace window.
        if (! $record->isRegular()) {
            return $record;
        }

        if ($record->isPaid()) {
            return $record;
        }

        if ($record->hold_reason) {
            $record->status = PayRecord::STATUS_HELD;

            return $record;
        }

        $resolved = $this->hoursResolver->resolveForRecord($record);
        $form = $resolved['form'];

        if ($form) {
            $record->compliance_form_id = $form->id;
            $record->program_tag = $resolved['program'] ?? $this->hoursResolver->resolveProgramTag($record, $form);
        }

        if (in_array($resolved['status'], [PayRecord::STATUS_AWAITING_FORM, PayRecord::STATUS_PENDING], true)) {
            $record->hours = null;
            $record->hours_source = null;
            $record->gross = null;
            $record->grace_end_date = null;
            $record->status = $resolved['status'];

            return $record;
        }

        $record->hours = $resolved['hours'];
        $record->hours_source = $resolved['hours_source'];
        $rate = $this->calculationService->resolveRate($record->employee?->hourly_wage);
        $this->calculationService->applyCalculation($record, $record->hours, $rate);

        if ($form?->submitted_at && $this->isLateSubmission($form, $record)) {
            $record->status = PayRecord::STATUS_LATE_ROLLED;
            $record->grace_end_date = null;

            return $record;
        }

        if ($form?->status === ComplianceForm::STATUS_VERIFIED && $form->submitted_at) {
            $record->verified_at = $form->submitted_at;
            $record->grace_end_date = $this->graceWindowService->graceEndDate($form->submitted_at)?->toDateString();
            $record->status = $this->graceWindowService->graceStatus($form->submitted_at, $asOf) ?? PayRecord::STATUS_READY;
        } else {
            $record->status = PayRecord::STATUS_PENDING;
        }

        return $record;
    }

    protected function isLateSubmission(ComplianceForm $form, PayRecord $record): bool
    {
        if (! $form->submitted_at || ! $record->period_key) {
            return false;
        }

        $periodEnd = Carbon::createFromFormat('Y-m', $record->period_key)->endOfMonth();
        $cutoffDay = (int) config('payroll.period_cutoff_day', 5);
        $cutoff = $periodEnd->copy()->addDays($cutoffDay)->endOfDay();

        return $form->submitted_at->greaterThan($cutoff);
    }

    public function appendLifecycleEvent(array $events, string $label, string $state): array
    {
        $events[] = [
            'label'      => $label,
            'state'      => $state,
            'occurred_at'=> now()->toIso8601String(),
        ];

        return $events;
    }

    public function buildLifecycleTimeline(PayRecord $record): array
    {
        $form = $record->complianceForm ?? $this->hoursResolver->findComplianceForm($record);
        $formVerified = $form?->status === ComplianceForm::STATUS_VERIFIED;
        $submitted = $formVerified ? $form?->submitted_at : null;
        $inGrace = $submitted && $this->graceWindowService->isInGrace($submitted);
        $graceCleared = $submitted && ! $inGrace;
        $inBatch = (bool) $record->batch_id;
        $paid = $record->isPaid();

        return [
            ['label' => 'Compliance form verified', 'state' => $formVerified ? 'completed' : 'pending'],
            ['label' => 'Grace window cleared', 'state' => $graceCleared ? 'completed' : ($inGrace ? 'in_progress' : 'pending')],
            ['label' => 'Added to batch', 'state' => $inBatch ? ($paid ? 'completed' : 'in_progress') : 'pending'],
            ['label' => 'Direct deposit', 'state' => $paid ? 'completed' : 'pending'],
            ['label' => 'Pay stub stored', 'state' => $paid && $record->stub_path ? 'completed' : 'pending'],
        ];
    }
}
