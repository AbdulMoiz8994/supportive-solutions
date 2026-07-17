<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\PayRecord;
use App\Models\Schedule;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => seedModuleBasics());

test('deleting client cascades to schedules', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $scheduleId = $schedule->id;
    Client::withoutGlobalScopes()->find($client->id)?->delete();

    expect(Schedule::withoutGlobalScopes()->find($scheduleId))->toBeNull();
});

test('schedule soft delete preserves record', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $schedule->delete();

    expect(Schedule::find($schedule->id))->toBeNull()
        ->and(Schedule::withTrashed()->find($schedule->id))->not->toBeNull();
});

test('payroll claim cascade deletes when pay record deleted', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $payRecord = payrollTestRecord($org->id, $employee->id, $client->id);

    $payrollClaim = \App\Models\PayrollClaim::create([
        'organization_id' => $org->id,
        'pay_record_id' => $payRecord->id,
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);

    $claimId = $payrollClaim->id;
    PayRecord::withoutGlobalScopes()->find($payRecord->id)?->delete();

    expect(\App\Models\PayrollClaim::find($claimId))->toBeNull();
});

test('critical tables have expected foreign key columns', function () {
    $tables = [
        'clients' => ['organization_id'],
        'employees' => ['organization_id'],
        'schedules' => ['organization_id', 'client_id', 'employee_id'],
        'billing_claim_audits' => ['organization_id', 'client_id'],
        'pay_records' => ['organization_id', 'employee_id', 'client_id'],
        'intakes' => ['organization_id'],
    ];

    foreach ($tables as $table => $columns) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist");

        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("{$table}.{$column} should exist");
        }
    }
});

test('unique email constraint prevents duplicate users', function () {
    $this->createUser(\App\Models\User::ROLE_ADMIN, ['email' => 'unique@example.com']);

    expect(fn () => $this->createUser(\App\Models\User::ROLE_STAFF, ['email' => 'unique@example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
