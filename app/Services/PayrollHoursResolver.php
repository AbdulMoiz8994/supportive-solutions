<?php

namespace App\Services;

use App\Models\ComplianceForm;
use App\Models\Employee;
use App\Models\PayRecord;
use Carbon\Carbon;

class PayrollHoursResolver
{
    public function __construct(
        protected PayrollCalculationService $calculationService
    ) {}

    public function resolveForRecord(PayRecord $record, ?ComplianceForm $form = null): array
    {
        $employee = $record->employee;
        $form = $form ?? $this->findComplianceForm($record);

        if (! $form) {
            return $this->unresolvedResult($form, PayRecord::STATUS_AWAITING_FORM);
        }

        if ($form->status === ComplianceForm::STATUS_SUBMITTED) {
            return $this->unresolvedResult($form, PayRecord::STATUS_PENDING);
        }

        if ($form->status !== ComplianceForm::STATUS_VERIFIED) {
            return $this->unresolvedResult($form, PayRecord::STATUS_AWAITING_FORM);
        }

        $program = $this->resolveProgramTag($record, $form);
        $hours = $this->resolveHours($employee, $form, $program, $record);
        $hoursSource = $this->resolveHoursSource($employee, $program);

        if ($hours === null) {
            return $this->unresolvedResult($form, PayRecord::STATUS_AWAITING_FORM, $program);
        }

        return [
            'hours'        => $hours,
            'hours_source' => $hoursSource,
            'status'       => null,
            'form'         => $form,
            'program'      => $program,
        ];
    }

    /**
     * @return array{hours: null, hours_source: null, status: string, form: ?ComplianceForm, program?: string}
     */
    protected function unresolvedResult(?ComplianceForm $form, string $status, ?string $program = null): array
    {
        return [
            'hours'        => null,
            'hours_source' => null,
            'status'       => $status,
            'form'         => $form,
            'program'      => $program,
        ];
    }

    public function findComplianceForm(PayRecord $record): ?ComplianceForm
    {
        if ($record->compliance_form_id) {
            return $record->complianceForm;
        }

        $periodKey = $record->period_key ?? $this->periodKeyFromLabel($record->period);

        if (! $periodKey) {
            return null;
        }

        return ComplianceForm::query()
            ->where('employee_id', $record->employee_id)
            ->when($record->client_id, fn ($q) => $q->where('client_id', $record->client_id))
            ->where('period', $periodKey)
            ->first();
    }

    public function resolveHours(?Employee $employee, ComplianceForm $form, string $program, ?PayRecord $record = null): ?float
    {
        if ($employee?->live_in || $employee?->evv_exempt) {
            return $form->delivered_hours !== null ? (float) $form->delivered_hours : null;
        }

        if ($program === 'DHS') {
            return $this->resolveDhsHours($form);
        }

        $evvHours = $record ? $this->resolveEvvHours($record) : null;

        if ($evvHours === null) {
            return null;
        }

        $complianceHours = $this->resolveMichHours($form);

        if ($complianceHours !== null) {
            return min($evvHours, $complianceHours);
        }

        return $evvHours;
    }

    public function requiresEvvHours(?Employee $employee, string $program): bool
    {
        if ($employee?->live_in || $employee?->evv_exempt) {
            return false;
        }

        return $program !== 'DHS';
    }

    public function resolveEvvHours(PayRecord $record): ?float
    {
        $periodKey = $record->period_key ?? $this->periodKeyFromLabel($record->period);

        if (! $periodKey || ! $record->employee_id) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m', $periodKey)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Shared clean-visit gate: flagged visits (missing clock-out, off-site,
        // impossible durations) never feed payroll hours.
        $total = app(VisitReportService::class)->payableHours(
            $record->organization_id,
            $start->toDateString(),
            $end->toDateString(),
            $record->client_id,
            $record->employee_id,
        );

        return $total > 0 ? $total : null;
    }

    protected function resolveDhsHours(ComplianceForm $form): ?float
    {
        $days = collect($form->days ?? []);
        $workedDays = $days->where('state', 'worked')->count();

        if ($workedDays > 0) {
            return (float) ($workedDays * 4);
        }

        return $form->delivered_hours !== null ? (float) $form->delivered_hours : null;
    }

    protected function resolveMichHours(ComplianceForm $form): ?float
    {
        $delivered = $form->delivered_hours !== null ? (float) $form->delivered_hours : null;

        if ($delivered === null) {
            return null;
        }

        $authorized = $form->authorized_hours !== null ? (float) $form->authorized_hours : null;

        if ($authorized !== null && $delivered > $authorized) {
            return $authorized;
        }

        return $delivered;
    }

    public function resolveHoursSource(?Employee $employee, string $program): string
    {
        if ($employee?->live_in || $employee?->evv_exempt) {
            return 'from compliance form';
        }

        if ($program === 'DHS') {
            return 'days-met logic';
        }

        return 'EVV clocked';
    }

    public function resolveProgramTag(PayRecord $record, ?ComplianceForm $form = null): string
    {
        if ($record->program_tag) {
            return $record->program_tag;
        }

        $assignment = $record->employee?->assignments
            ?->firstWhere('client_id', $record->client_id);

        $program = $assignment?->program ?? '';

        if (stripos($program, 'DHS') !== false) {
            return 'DHS';
        }

        return 'MICH';
    }

    public function periodKeyFromLabel(?string $periodLabel): ?string
    {
        if (! $periodLabel) {
            return null;
        }

        try {
            return Carbon::createFromFormat('F Y', trim($periodLabel))->format('Y-m');
        } catch (\Throwable) {
            if (preg_match('/^\d{4}-\d{2}$/', trim($periodLabel))) {
                return trim($periodLabel);
            }
        }

        return null;
    }
}
