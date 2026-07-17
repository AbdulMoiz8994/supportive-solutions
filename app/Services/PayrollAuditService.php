<?php

namespace App\Services;

use App\Models\PayRecord;
use App\Models\PayrollAuditLog;
use App\Models\PayrollBatch;
use App\Models\User;

class PayrollAuditService
{
    public function log(
        User $actor,
        string $action,
        ?PayRecord $record = null,
        ?string $before = null,
        ?string $after = null,
        ?string $detail = null,
        ?int $organizationId = null
    ): PayrollAuditLog {
        return PayrollAuditLog::withoutGlobalScopes()->create([
            'organization_id' => $organizationId ?? $record?->organization_id ?? $actor->organization_id,
            'pay_record_id'   => $record?->id,
            'actor_name'      => $actor->name,
            'actor_role'      => $actor->role,
            'action'          => $action,
            'value_before'    => $before,
            'value_after'     => $after,
            'detail'          => $detail,
            'occurred_at'     => now(),
        ]);
    }

    public function logWageUpdate(PayRecord $record, User $actor, ?string $before, ?string $after): PayrollAuditLog
    {
        return $this->log($actor, 'wage_update', $record, $before, $after, 'Hourly wage updated');
    }

    public function logHoldRelease(PayRecord $record, User $actor, ?string $before, ?string $note = null): PayrollAuditLog
    {
        return $this->log($actor, 'hold_release', $record, $before, null, $note);
    }

    public function logHoldApplied(PayRecord $record, User $actor, string $reason): PayrollAuditLog
    {
        return $this->log($actor, 'hold_apply', $record, null, $reason, 'Payroll hold applied');
    }

    public function logBatchBuild(int $organizationId, User $actor, int $batchId, int $recordCount): PayrollAuditLog
    {
        return $this->log(
            $actor,
            'batch_build',
            null,
            null,
            "Batch #{$batchId} built with {$recordCount} records",
            organizationId: $organizationId
        );
    }

    public function logBatchRecord(PayRecord $record, User $actor): PayrollAuditLog
    {
        return $this->log($actor, 'batch_build', $record, null, 'Added to batch');
    }

    public function logExport(User $actor, ?int $organizationId, string $periodKey, int $rowCount): PayrollAuditLog
    {
        return $this->log(
            $actor,
            'export',
            null,
            null,
            "{$rowCount} rows exported for {$periodKey}",
            organizationId: $organizationId
        );
    }

    /**
     * @param  list<string>|array<int, string>  $messages
     */
    public function logAwBatchSync(
        User $actor,
        PayrollBatch $batch,
        int $recordCount,
        ?int $payrollId,
        array $messages = []
    ): PayrollAuditLog {
        $detail = $payrollId
            ? "AW payroll ID {$payrollId}, {$recordCount} record(s)"
            : "{$recordCount} record(s)";

        if ($messages !== []) {
            $detail .= ' — '.implode(' ', array_map('strval', $messages));
        }

        return $this->log(
            $actor,
            'aw_payroll_sync',
            null,
            null,
            "Batch #{$batch->id} synced to AccountantsWorld",
            $detail,
            $batch->organization_id
        );
    }

    public function logStubAccess(PayRecord $record, User $actor): PayrollAuditLog
    {
        return $this->log($actor, 'stub_download', $record, null, $record->stub_path);
    }
}
