<?php

namespace App\Services;

use App\Models\ComplianceForm;
use App\Models\PayRecord;
use App\Models\PayrollBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollBatchService
{
    public function __construct(
        protected PayrollRecordWorkflowService $recordWorkflow,
        protected PayrollGraceWindowService $graceWindowService,
        protected PayrollAuditService $auditService,
        protected \App\Services\Directory\IntegrationConnectionHealthRecorder $integrationHealth,
    ) {}

    public function batchDatesForPeriod(Carbon $period): array
    {
        $followingMonth = $period->copy()->addMonth()->startOfMonth();
        $buildDate = $this->firstTuesday($followingMonth);
        $payDate = $this->followingFriday($buildDate);

        return [
            'build_date' => $buildDate,
            'pay_date'   => $payDate,
        ];
    }

    public function firstTuesday(Carbon $month): Carbon
    {
        $date = $month->copy()->startOfMonth();

        while ($date->dayOfWeek !== Carbon::TUESDAY) {
            $date->addDay();
        }

        return $date;
    }

    public function followingFriday(Carbon $fromDate): Carbon
    {
        $date = $fromDate->copy()->addDay();

        while ($date->dayOfWeek !== Carbon::FRIDAY) {
            $date->addDay();
        }

        return $date;
    }

    public function buildBatch(?int $organizationId, Carbon $period, User $actor, array $requestedIds = []): PayrollBatch
    {
        return DB::transaction(function () use ($organizationId, $period, $actor, $requestedIds) {
            $periodKey = $period->format('Y-m');

            $existing = PayrollBatch::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('period_key', $periodKey)
                ->where('status', 'built')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $records = PayRecord::withoutGlobalScopes()
                ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
                ->where('period_key', $periodKey)
                ->whereNull('batch_id')
                ->with(['employee.assignments', 'client', 'complianceForm'])
                ->get()
                ->map(function (PayRecord $record) {
                    $this->recordWorkflow->refreshRecord($record);
                    $record->saveQuietly();

                    return $record;
                });

            $eligible = $records->filter(function (PayRecord $record) use ($requestedIds) {
                if (! empty($requestedIds) && ! in_array($record->id, $requestedIds, true)) {
                    return false;
                }

                return $this->isEligibleForBatch($record);
            });

            $dates = $this->batchDatesForPeriod($period);

            $batch = PayrollBatch::withoutGlobalScopes()->create([
                'organization_id' => $organizationId,
                'period_key'      => $periodKey,
                'build_date'      => $dates['build_date'],
                'pay_date'        => $dates['pay_date'],
                'record_count'    => $eligible->count(),
                'total_gross'     => $eligible->sum('gross'),
                'built_by'        => $actor->id,
                'built_at'        => now(),
                'status'          => 'built',
            ]);

            foreach ($eligible as $record) {
                $record->batch_id = $batch->id;
                $record->lifecycle_events = $this->recordWorkflow->appendLifecycleEvent(
                    $record->lifecycle_events ?? [],
                    'Added to batch',
                    'completed'
                );
                $record->saveQuietly();

                $this->auditService->logBatchRecord($record, $actor);
            }

            $this->auditService->logBatchBuild($organizationId, $actor, $batch->id, $eligible->count());

            $this->integrationHealth->recordBatch(\App\Models\IntegrationCredential::KEY_ACCOUNTANTSWORLD);

            return $batch->fresh(['payRecords']);
        });
    }

    public function isEligibleForBatch(PayRecord $record): bool
    {
        if ($record->hold_reason || $record->batch_id || $record->isPaid()) {
            return false;
        }

        if ($record->status !== PayRecord::STATUS_READY) {
            return false;
        }

        $record->loadMissing('complianceForm');

        if ($record->complianceForm?->status !== ComplianceForm::STATUS_VERIFIED) {
            return false;
        }

        if ($record->hours === null || $record->gross === null || (float) $record->hours <= 0) {
            return false;
        }

        if ($record->complianceForm?->submitted_at) {
            if ($this->graceWindowService->isInGrace($record->complianceForm->submitted_at)) {
                return false;
            }
        } elseif ($record->grace_end_date && now()->startOfDay()->lt($record->grace_end_date)) {
            return false;
        }

        return true;
    }
}
