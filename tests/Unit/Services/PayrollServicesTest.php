<?php

use App\Models\ComplianceForm;
use App\Models\PayRecord;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollEligibilityService;
use App\Services\PayrollGraceWindowService;
use App\Services\PayrollHoursResolver;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createPayRecord(int $orgId, int $employeeId, int $clientId, array $attributes = []): PayRecord
{
    return payrollTestRecord($orgId, $employeeId, $clientId, $attributes);
}

function createComplianceForm(int $orgId, int $employeeId, int $clientId, array $attributes = []): ComplianceForm
{
    return ComplianceForm::withoutGlobalScopes()->create(array_merge([
        'organization_id'  => $orgId,
        'employee_id'      => $employeeId,
        'client_id'        => $clientId,
        'period'           => '2026-05',
        'period_label'     => 'May 2026',
        'status'           => 'Verified',
        'delivered_hours'  => 108,
        'authorized_hours' => 120,
        'submitted_at'     => now()->subDays(15),
        'service_start'    => '2026-05-01',
        'service_end'      => '2026-05-31',
    ], $attributes));
}

// --- PayrollCalculationServiceTest ---

test('gross equals hours times rate with decimal precision', function () {
    $service = app(PayrollCalculationService::class);

    expect($service->calculateGross(108, 15))->toBe(1620.00);
    expect($service->calculateGross(10.5, 15.25))->toBe(160.13);
});

test('zero hours yields zero gross', function () {
    $service = app(PayrollCalculationService::class);

    expect($service->calculateGross(0, 15))->toBe(0.0);
});

test('rate change recalculates gross on record', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['hourly_wage' => 15.00]);
    $record = createPayRecord($org->id, $employee->id, $client->id);

    $service = app(PayrollCalculationService::class);
    $updated = $service->applyCalculation($record, 108, 16.50);

    expect((float) $updated->gross)->toBe(1782.00);
});

test('wage edit does not mutate client billing rate', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['billing_rate' => 30.00]);
    $employee = $this->createEmployee($org->id, ['hourly_wage' => 15.00]);
    $record = createPayRecord($org->id, $employee->id, $client->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    app(\App\Services\PayrollService::class)->updateWage($record, 18.00, $admin);

    expect((float) $client->fresh()->billing_rate)->toBe(30.00);
});

// --- PayrollGraceWindowServiceTest ---

test('grace end is submitted_at plus ten days', function () {
    $service = app(PayrollGraceWindowService::class);
    $submitted = Carbon::parse('2026-05-01 23:59:00');

    expect($service->graceEndDate($submitted)?->toDateString())->toBe('2026-05-11');
});

test('status is in grace when today is before grace end', function () {
    $service = app(PayrollGraceWindowService::class);
    $submitted = now()->subDays(3);

    expect($service->isInGrace($submitted))->toBeTrue();
    expect($service->graceStatus($submitted))->toBe(PayRecord::STATUS_IN_GRACE);
});

test('status transitions to ready when grace cleared', function () {
    $service = app(PayrollGraceWindowService::class);
    $submitted = now()->subDays(15);

    expect($service->isInGrace($submitted))->toBeFalse();
    expect($service->graceStatus($submitted))->toBe(PayRecord::STATUS_READY);
});

// --- PayrollHoursResolverTest ---

test('live-in caregiver hours come from compliance form', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $record = createPayRecord($org->id, $employee->id, $client->id);
    $form = createComplianceForm($org->id, $employee->id, $client->id, ['delivered_hours' => 108]);
    $record->compliance_form_id = $form->id;

    $resolver = app(PayrollHoursResolver::class);
    $result = $resolver->resolveForRecord($record, $form);

    expect($result['hours'])->toBe(108.0);
    expect($result['hours_source'])->toBe('from compliance form');
});

test('mich caps delivered hours at authorized hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $form = createComplianceForm($org->id, $employee->id, $client->id, [
        'delivered_hours'  => 130,
        'authorized_hours' => 120,
    ]);

    foreach (['2026-05-10', '2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15', '2026-05-16', '2026-05-17', '2026-05-18', '2026-05-19'] as $date) {
        $this->createSchedule($org->id, $client->id, $employee->id, [
            'status'      => \App\Models\Schedule::STATUS_COMPLETED,
            'date'        => $date,
            'total_hours' => 13,
            'evv_status'  => true,
        ]);
    }

    $record = createPayRecord($org->id, $employee->id, $client->id);

    $resolver = app(PayrollHoursResolver::class);
    $hours = $resolver->resolveHours($employee, $form, 'MICH', $record);

    expect($hours)->toBe(120.0);
});

test('missing compliance form yields awaiting form status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = createPayRecord($org->id, $employee->id, $client->id, ['status' => PayRecord::STATUS_AWAITING_FORM]);

    $result = app(PayrollHoursResolver::class)->resolveForRecord($record);

    expect($result['status'])->toBe(PayRecord::STATUS_AWAITING_FORM);
});

test('submitted but unverified compliance form yields pending status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $record = createPayRecord($org->id, $employee->id, $client->id);
    $form = createComplianceForm($org->id, $employee->id, $client->id, [
        'status' => ComplianceForm::STATUS_SUBMITTED,
    ]);
    $record->compliance_form_id = $form->id;

    $result = app(PayrollHoursResolver::class)->resolveForRecord($record, $form);

    expect($result['status'])->toBe(PayRecord::STATUS_PENDING);
});

test('mich non exempt requires evv hours even when compliance form has delivered hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $form = createComplianceForm($org->id, $employee->id, $client->id, ['delivered_hours' => 108]);

    $hours = app(PayrollHoursResolver::class)->resolveHours($employee, $form, 'MICH');

    expect($hours)->toBeNull();
});

// --- PayrollEligibilityTest ---

test('eligible from is max of case start and champs date', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'champs_association_date' => '2026-02-01',
        'pay_eligibility_start'   => null,
    ]);

    \App\Models\CaregiverAssignment::withoutGlobalScopes()->create([
        'organization_id'  => $org->id,
        'employee_id'      => $employee->id,
        'client_id'        => $this->createClient($org->id)->id,
        'status'           => 'Active',
        'assigned_since'   => '2026-03-01',
    ]);

    $eligible = app(PayrollEligibilityService::class)->resolveEligibleFrom($employee->fresh());

    expect($eligible->toDateString())->toBe('2026-03-01');
});

test('family caregiver backdating allowed to eligible from', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, [
        'caregiver_type'        => 'Family caregiver',
        'pay_eligibility_start' => '2026-02-01',
    ]);
    $record = createPayRecord($org->id, $employee->id, $client->id, [
        'caregiver_type' => PayRecord::CAREGIVER_FAMILY,
        'period_key'     => '2026-03',
    ]);

    $allowed = app(PayrollEligibilityService::class)->canBackdateToPeriod(
        $record,
        Carbon::create(2026, 3, 1)
    );

    expect($allowed)->toBeTrue();
});

test('agency-sourced backdating is rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['caregiver_type' => 'Agency-sourced']);
    $record = createPayRecord($org->id, $employee->id, $client->id, [
        'caregiver_type' => PayRecord::CAREGIVER_AGENCY,
        'period_key'     => '2026-01',
    ]);

    expect(fn () => app(PayrollEligibilityService::class)->assertBackdatingAllowed($record))
        ->toThrow(\InvalidArgumentException::class);
});
