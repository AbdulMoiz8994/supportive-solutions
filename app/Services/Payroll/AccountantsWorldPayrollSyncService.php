<?php

namespace App\Services\Payroll;

use App\Models\IntegrationCredential;
use App\Models\PayRecord;
use App\Models\PayrollBatch;
use App\Models\User;
use App\Services\Directory\IntegrationConnectionHealthRecorder;
use App\Services\PayrollAuditService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountantsWorldPayrollSyncService
{
    public const HELD_STATUSES = ['Held - review', 'Held - billing'];

    public function __construct(
        protected AccountantsWorldClient $client,
        protected AccountantsWorldErrorFormatter $errorFormatter,
        protected PayrollAuditService $auditService,
        protected IntegrationConnectionHealthRecorder $integrationHealth,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     payroll_id: ?int,
     *     pay_stub_count: int,
     *     synced_record_count: int
     * }
     */
    public function syncBatch(PayrollBatch $batch, User $actor): array
    {
        $batch->loadMissing(['payRecords.employee']);

        $exportable = $this->exportableRecords($batch);

        if ($exportable->isEmpty()) {
            return $this->failBatch($batch, 'No exportable caregivers in this batch (all may be on hold).');
        }

        $missingAwIds = $exportable
            ->filter(fn (PayRecord $record) => blank($record->employee?->aw_employee_id))
            ->map(fn (PayRecord $record) => trim("{$record->employee?->first_name} {$record->employee?->last_name}"))
            ->filter()
            ->unique()
            ->values();

        if ($missingAwIds->isNotEmpty()) {
            return $this->failBatch(
                $batch,
                'These caregivers need an AccountantsWorld employee ID before payroll export: '.$missingAwIds->implode(', ').'.'
            );
        }

        try {
            $payScheduleId = $this->resolvePayScheduleId();
        } catch (InvalidArgumentException $exception) {
            return $this->failBatch($batch, $exception->getMessage());
        }

        $nextPayroll = $this->client->getNextPayrollData($payScheduleId);

        if (! $nextPayroll['success']) {
            $message = $this->errorFormatter->formatFromApiResult($nextPayroll, AccountantsWorldErrorFormatter::CONTEXT_PAYROLL_SYNC);

            return $this->failBatch($batch, 'Could not load next payroll from AccountantsWorld. '.$message);
        }

        $payrollPayload = is_array($nextPayroll['data']) ? $nextPayroll['data'] : [];
        $mapping = $this->applyBatchHours($payrollPayload, $exportable);

        if ($mapping['errors'] !== []) {
            return $this->failBatch($batch, implode(' ', $mapping['errors']));
        }

        $update = $this->client->updatePayrollData($mapping['payload']);

        if (! $update['success']) {
            return $this->failBatch(
                $batch,
                'AccountantsWorld rejected the payroll update. '.$this->formatUpdateFailure($update)
            );
        }

        $payrollId = (int) Arr::get($payrollPayload, 'keyData.payrollId', 0);
        $payPeriod = Arr::get($payrollPayload, 'keyData.payPeriod', []);
        $payrollDetails = $this->fetchPayrollDetails($payPeriod, $batch->period_key);
        $stubResult = $payrollId > 0
            ? $this->client->getPayrollPayStubs($payrollId)
            : ['success' => false, 'data' => []];

        $stubs = ($stubResult['success'] && is_array($stubResult['data'])) ? $stubResult['data'] : [];
        $payStubCount = count($stubs);

        $payrollMeta = [
            'payScheduleId' => $payScheduleId,
            'payrollId' => $payrollId > 0 ? $payrollId : null,
            'updateMessages' => Arr::get($update, 'data.messages', []),
            'payPeriod' => is_array($payPeriod) ? $payPeriod : null,
            'payrollDetails' => $payrollDetails['data'] ?? null,
            'payrollDetailsVerified' => $payrollDetails['success'] ?? false,
            'payStubCount' => $payStubCount,
            'payStubs' => $this->summarizePayStubs($stubs),
            'syncedAt' => now()->toIso8601String(),
        ];

        DB::transaction(function () use ($batch, $exportable, $payScheduleId, $payrollId, $update, $actor, $payrollMeta, $stubs) {
            $batch->update([
                'status' => 'synced',
                'approval_status' => 'exported',
                'aw_pay_schedule_id' => $payScheduleId,
                'aw_payroll_id' => $payrollId > 0 ? $payrollId : null,
                'aw_sync_error' => null,
                'aw_synced_at' => now(),
                'aw_payroll_meta' => $payrollMeta,
            ]);

            $this->applyPayStubsToRecords($exportable, $stubs);

            foreach ($exportable as $record) {
                $record->exported_at = now();
                $record->lifecycle_events = app(\App\Services\PayrollRecordWorkflowService::class)->appendLifecycleEvent(
                    $record->lifecycle_events ?? [],
                    'Exported to AccountantsWorld',
                    'completed'
                );
                $record->saveQuietly();
            }

            $this->auditService->logAwBatchSync(
                $actor,
                $batch,
                $exportable->count(),
                $payrollId > 0 ? $payrollId : null,
                Arr::get($update, 'data.messages', [])
            );
        });

        $this->integrationHealth->recordBatch(IntegrationCredential::KEY_ACCOUNTANTSWORLD);

        $messages = collect(Arr::get($update, 'data.messages', []))
            ->filter()
            ->implode(' ');

        $successMessage = "Batch #{$batch->id} synced to AccountantsWorld ({$exportable->count()} caregiver(s)";
        if ($payrollId > 0) {
            $successMessage .= ", payroll ID {$payrollId}";
        }
        if ($payStubCount > 0) {
            $successMessage .= ", {$payStubCount} pay stub(s) retrieved";
        }
        if ($payrollDetails['success'] ?? false) {
            $successMessage .= ', payroll details verified';
        }
        $successMessage .= ').';
        if ($messages !== '') {
            $successMessage .= ' '.$messages;
        }

        return [
            'success' => true,
            'message' => $successMessage,
            'payroll_id' => $payrollId > 0 ? $payrollId : null,
            'pay_stub_count' => $payStubCount,
            'synced_record_count' => $exportable->count(),
        ];
    }

    /**
     * @return array{success: bool, message: string, payroll_id: ?int, pay_stub_count: int, synced_record_count: int}
     */
    protected function failBatch(PayrollBatch $batch, string $message): array
    {
        $batch->update(['aw_sync_error' => $message]);

        return [
            'success' => false,
            'message' => $message,
            'payroll_id' => null,
            'pay_stub_count' => 0,
            'synced_record_count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payPeriod
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    public function fetchPayrollDetails(?array $payPeriod, string $periodKey): array
    {
        $start = $this->resolvePayPeriodDate($payPeriod, 'startDate', $periodKey, startOfMonth: true);
        $end = $this->resolvePayPeriodDate($payPeriod, 'endDate', $periodKey, startOfMonth: false);

        if (! $start || ! $end) {
            return [
                'success' => false,
                'data' => null,
                'raw' => ['message' => 'Pay period dates were unavailable for payroll details lookup.'],
            ];
        }

        return $this->client->getPayrollDetails($start, $end);
    }

    /**
     * @param  array<string, mixed>|null  $payPeriod
     */
    protected function resolvePayPeriodDate(
        ?array $payPeriod,
        string $key,
        string $periodKey,
        bool $startOfMonth
    ): ?string {
        $fromPeriod = Arr::get($payPeriod, $key);

        if (is_string($fromPeriod) && $fromPeriod !== '') {
            return Carbon::parse($fromPeriod)->toIso8601String();
        }

        $fallback = Carbon::createFromFormat('Y-m', $periodKey);

        return ($startOfMonth ? $fallback->copy()->startOfMonth() : $fallback->copy()->endOfMonth())->toIso8601String();
    }

    /**
     * @param  list<array<string, mixed>>  $stubs
     * @return list<array<string, mixed>>
     */
    public function summarizePayStubs(array $stubs): array
    {
        return collect($stubs)
            ->filter(fn ($stub) => is_array($stub))
            ->map(fn (array $stub) => [
                'paycheckID' => $stub['paycheckID'] ?? null,
                'empID' => $stub['empID'] ?? null,
                'grossPay' => $stub['grossPay'] ?? null,
                'netPay' => $stub['netPay'] ?? null,
                'payDate' => $stub['payDate'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PayRecord>  $records
     * @param  list<array<string, mixed>>  $stubs
     */
    public function applyPayStubsToRecords(Collection $records, array $stubs): void
    {
        if ($stubs === []) {
            return;
        }

        $stubsByEmpId = collect($stubs)
            ->filter(fn ($stub) => is_array($stub) && isset($stub['empID']))
            ->keyBy(fn (array $stub) => (int) $stub['empID']);

        foreach ($stubsByEmpId as $empId => $stub) {
            $employeeRecords = $records->filter(
                fn (PayRecord $record) => (int) ($record->employee?->aw_employee_id ?? 0) === (int) $empId
            );

            if ($employeeRecords->isEmpty()) {
                continue;
            }

            $grossPay = isset($stub['grossPay']) ? (float) $stub['grossPay'] : null;
            $netPay = isset($stub['netPay']) ? (float) $stub['netPay'] : null;
            $totalHours = (float) $employeeRecords->sum(fn (PayRecord $record) => (float) ($record->hours ?? 0));
            $workflow = app(\App\Services\PayrollRecordWorkflowService::class);

            foreach ($employeeRecords as $record) {
                $share = $totalHours > 0
                    ? ((float) ($record->hours ?? 0) / $totalHours)
                    : (1 / max(1, $employeeRecords->count()));

                if ($grossPay !== null) {
                    $record->gross = round($grossPay * $share, 2);
                }

                if ($netPay !== null) {
                    $record->lifecycle_events = $workflow->appendLifecycleEvent(
                        $record->lifecycle_events ?? [],
                        'AW net pay: $'.number_format(round($netPay * $share, 2), 2),
                        'completed'
                    );
                }

                if (isset($stub['paycheckID'])) {
                    $record->lifecycle_events = $workflow->appendLifecycleEvent(
                        $record->lifecycle_events ?? [],
                        'AW paycheck #'.$stub['paycheckID'],
                        'completed'
                    );
                }

                $record->saveQuietly();
            }
        }
    }

    /**
     * @return Collection<int, PayRecord>
     */
    public function exportableRecords(PayrollBatch $batch): Collection
    {
        return $batch->payRecords
            ->reject(fn (PayRecord $record) => in_array($record->status, self::HELD_STATUSES, true))
            ->values();
    }

    public function resolvePayScheduleId(): int
    {
        $configured = config('payroll.accountants_world_pay_schedule_id');

        if (filled($configured)) {
            return (int) $configured;
        }

        $schedules = $this->client->getPaySchedules();

        if (! $schedules['success']) {
            throw new InvalidArgumentException(
                'Could not load pay schedules from AccountantsWorld. Set ACCOUNTANTSWORLD_PAY_SCHEDULE_ID or verify API credentials.'
            );
        }

        $items = is_array($schedules['data']) ? $schedules['data'] : [];

        if ($items === []) {
            throw new InvalidArgumentException('AccountantsWorld returned no pay schedules for this client.');
        }

        $employeeSchedule = collect($items)
            ->first(fn ($schedule) => is_array($schedule) && ! ($schedule['forContractors'] ?? false));

        $selected = $employeeSchedule ?? $items[0];
        $payScheduleId = (int) ($selected['payScheduleId'] ?? 0);

        if ($payScheduleId <= 0) {
            throw new InvalidArgumentException('AccountantsWorld pay schedule response did not include a payScheduleId.');
        }

        return $payScheduleId;
    }

    /**
     * @param  array<string, mixed>  $payrollPayload
     * @param  Collection<int, PayRecord>  $records
     * @return array{payload: array<string, mixed>, errors: list<string>}
     */
    public function applyBatchHours(array $payrollPayload, Collection $records): array
    {
        $hoursByEmpId = $this->aggregateHoursByAwEmployeeId($records);
        $timeData = $payrollPayload['timeData'] ?? [];
        $defaultPayTypeCode = (string) config('payroll.accountants_world_default_pay_type_code', 'REG');
        $matchedEmpIds = [];
        $updatedTimeData = [];

        foreach ($timeData as $row) {
            if (! is_array($row)) {
                continue;
            }

            $empId = (int) ($row['empId'] ?? 0);

            if (! isset($hoursByEmpId[$empId])) {
                $updatedTimeData[] = $row;

                continue;
            }

            $matchedEmpIds[] = $empId;
            $local = $hoursByEmpId[$empId];
            $updatedTimeData[] = $this->applyHoursToTimeRow(
                $row,
                (float) $local['hours'],
                (float) $local['rate'],
                $defaultPayTypeCode
            );
        }

        $unmatchedEmpIds = array_diff(array_keys($hoursByEmpId), $matchedEmpIds);

        if ($unmatchedEmpIds !== []) {
            $names = collect($unmatchedEmpIds)
                ->map(fn (int $empId) => $hoursByEmpId[$empId]['name'] ?? "AW employee {$empId}")
                ->implode(', ');

            return [
                'payload' => $payrollPayload,
                'errors' => ["These caregivers were not found on the open AccountantsWorld payroll: {$names}."],
            ];
        }

        $payrollPayload['timeData'] = $updatedTimeData;

        return [
            'payload' => $payrollPayload,
            'errors' => [],
        ];
    }

    /**
     * @param  Collection<int, PayRecord>  $records
     * @return array<int, array{hours: float, rate: float, name: string}>
     */
    protected function aggregateHoursByAwEmployeeId(Collection $records): array
    {
        $map = [];

        foreach ($records as $record) {
            $awEmployeeId = (int) ($record->employee?->aw_employee_id ?? 0);

            if ($awEmployeeId <= 0) {
                continue;
            }

            if (! isset($map[$awEmployeeId])) {
                $map[$awEmployeeId] = [
                    'hours' => 0.0,
                    'rate' => (float) ($record->rate ?? $record->employee?->hourly_wage ?? config('payroll.wage.default_hourly', 15.00)),
                    'name' => trim("{$record->employee?->first_name} {$record->employee?->last_name}"),
                ];
            }

            $map[$awEmployeeId]['hours'] += (float) ($record->hours ?? 0);

            if ($record->rate !== null) {
                $map[$awEmployeeId]['rate'] = (float) $record->rate;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function applyHoursToTimeRow(array $row, float $hours, float $rate, string $defaultPayTypeCode): array
    {
        $hours = round(max(0, $hours), 2);
        $amount = round($hours * max(0, $rate), 2);
        $payTypes = $row['payTypes'] ?? [];

        if ($payTypes === []) {
            $row['payTypes'] = [[
                'payTypeCode' => $defaultPayTypeCode,
                'payTypeDesc' => null,
                'hours' => $hours,
                'amount' => $amount,
            ]];

            return $row;
        }

        $matchedDefault = false;

        foreach ($payTypes as $index => $payType) {
            if (! is_array($payType)) {
                continue;
            }

            if (($payType['payTypeCode'] ?? '') === $defaultPayTypeCode) {
                $payTypes[$index]['hours'] = $hours;
                $payTypes[$index]['amount'] = $amount;
                $matchedDefault = true;

                break;
            }
        }

        if (! $matchedDefault && isset($payTypes[0]) && is_array($payTypes[0])) {
            $payTypes[0]['hours'] = $hours;
            $payTypes[0]['amount'] = $amount;
        }

        $row['payTypes'] = $payTypes;

        return $row;
    }

    /**
     * @param  array{success?: bool, raw?: array<string, mixed>, data?: mixed}  $update
     */
    protected function formatUpdateFailure(array $update): string
    {
        $messages = Arr::get($update, 'data.messages');

        if (is_array($messages) && $messages !== []) {
            return implode(' ', array_map('strval', $messages));
        }

        return $this->errorFormatter->formatFromApiResult($update, AccountantsWorldErrorFormatter::CONTEXT_PAYROLL_SYNC);
    }
}
