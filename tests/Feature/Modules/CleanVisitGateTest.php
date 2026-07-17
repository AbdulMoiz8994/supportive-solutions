<?php

use App\Models\Schedule;
use App\Models\User;
use App\Services\Billing\BillingClaimGenerationService;
use App\Services\PayrollHoursResolver;
use App\Services\ScheduleClockService;
use App\Services\VisitReportService;
use Carbon\Carbon;

beforeEach(fn () => seedModuleBasics());

test('visit over the max duration cap resolves needs review and is not billable', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->subDays(2)->toDateString(),
        'actual_clock_in' => today()->subDays(2)->setTime(8, 0),
        'actual_clock_out' => today()->subDay()->setTime(14, 20), // 30.3h span
        'total_hours' => 30.33,
        'evv_status' => true,
    ]);

    $service = app(VisitReportService::class);

    expect($service->resolveReportStatus($schedule))->toBe(VisitReportService::STATUS_NEEDS_REVIEW)
        ->and($service->isBillable($schedule))->toBeFalse()
        ->and($service->hasCleanTimeData($schedule))->toBeFalse();
});

test('payable hours excludes flagged visits but keeps clean ones', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    // Clean completed visit — counts.
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-10',
        'total_hours' => 4,
        'evv_status' => true,
    ]);

    // Impossible 33.63-hour visit — must never inflate billing/payroll.
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-11',
        'total_hours' => 33.63,
        'evv_status' => true,
    ]);

    $hours = app(VisitReportService::class)
        ->payableHours($org->id, '2026-05-01', '2026-05-31', $client->id, $caregiver->id);

    expect($hours)->toBe(4.0);
});

test('payroll evv hours exclude abnormal-duration visits', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-12',
        'total_hours' => 6,
        'evv_status' => true,
    ]);
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-13',
        'total_hours' => 30.3,
        'evv_status' => true,
    ]);

    $record = payrollTestRecord($org->id, $caregiver->id, $client->id, [
        'period' => 'May 2026',
        'period_key' => '2026-05',
    ]);

    expect(app(PayrollHoursResolver::class)->resolveEvvHours($record))->toBe(6.0);
});

test('claim generation sums only clean visit hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['billing_rate' => 30]);
    $caregiver = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-10',
        'total_hours' => 4,
        'evv_status' => true,
    ]);
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-11',
        'total_hours' => 33.63,
        'evv_status' => true,
    ]);

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $result = app(BillingClaimGenerationService::class)
        ->generateForClient($client->fresh(['coverageType', 'employees', 'careDetails']), Carbon::parse('2026-05-01'));

    $claim = \App\Models\BillingClaimAudit::withoutGlobalScopes()
        ->where('client_id', $client->id)
        ->first();

    expect($result)->not->toBe('skipped')
        ->and($claim)->not->toBeNull()
        ->and((float) $claim->total_hours)->toBe(4.0)
        ->and((float) $claim->verified_hours)->toBe(4.0)
        ->and((float) $claim->completed_visit_hours)->toBe(37.63);

    $payload = app(\App\Services\Billing\BillingClaimSubmissionService::class)->buildAvailityPayload($claim);
    expect($payload['serviceLines'][0]['hours'])->toBe(4.0)
        ->and($payload['serviceLines'][0]['units'])->toBe(16);
});

test('clock out beyond the duration cap does not auto-verify evv', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'date' => today()->subDays(2)->toDateString(),
        'actual_clock_in' => now()->subHours(30),
    ]);

    $updated = app(ScheduleClockService::class)->clockOut($schedule);

    expect($updated->status)->toBe(Schedule::STATUS_COMPLETED)
        ->and((bool) $updated->evv_status)->toBeFalse()
        ->and((float) $updated->total_hours)->toBeGreaterThan(VisitReportService::maxVisitHours())
        ->and(data_get($updated->metadata, 'duration_flag.hours'))->not->toBeNull();

    // And the flagged visit surfaces as Needs review.
    expect(app(VisitReportService::class)->resolveReportStatus($updated))
        ->toBe(VisitReportService::STATUS_NEEDS_REVIEW);
});

test('normal clock out still auto-verifies evv', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'date' => today()->toDateString(),
        'actual_clock_in' => now()->subHours(3),
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $updated = app(ScheduleClockService::class)->clockOut($schedule, null, 42.3314, -83.0458);

    expect((bool) $updated->evv_status)->toBeTrue()
        ->and(data_get($updated->metadata, 'duration_flag'))->toBeNull();
});

test('authorizations page shows reassessment due for overdue DHS and verify program for missing coverage', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $dhsType = \App\Models\CoverageType::firstOrCreate(['name' => 'DHS Home Help']);

    $dhs = $this->createClient($org->id, ['coverage_type_id' => $dhsType->id, 'first_name' => 'Dhs', 'last_name' => 'Client']);
    $unknown = $this->createClient($org->id, ['first_name' => 'NoProgram', 'last_name' => 'Client']);

    foreach ([$dhs, $unknown] as $client) {
        \App\Models\CareDetail::create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'start_date' => today()->subMonths(8),
            'end_date' => today()->subDays(5),
            'status' => 'Active',
            'total_units' => 100,
        ]);
    }

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('authorizations'))
        ->assertOk()
        ->assertSee('Dhs Client')
        ->assertSee('Time/Task')
        ->assertSee('Reassessment due')
        ->assertSee('Verify program');

    // DHS must never read "Expired Nd ago"; unknown coverage must not masquerade
    // as an expired prior auth either.
    $response->assertDontSee('d ago');
});

test('client with missing coverage type never reads expired', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    \App\Models\CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'start_date' => today()->subMonths(8),
        'end_date' => today()->subDays(5),
        'status' => 'Active',
        'total_units' => 100,
    ]);

    $client->load('coverageType', 'careDetails');

    expect($client->authStatus()['label'])->toBe('Verify program')
        ->and($client->authStatus()['tone'])->toBe('amber');
});

test('compliance page and reports use the same monthly forms definition', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $clientA = $this->createClient($org->id, ['first_name' => 'Form', 'last_name' => 'In']);
    $this->createClient($org->id, ['first_name' => 'Form', 'last_name' => 'Missing']);

    \App\Models\ComplianceForm::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'client_id' => $clientA->id,
        'period' => now()->format('Y-m'),
        'status' => \App\Models\ComplianceForm::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $stats = app(\App\Services\RegistryMetricsService::class)->complianceFormStats($org->id);

    expect($stats['total'])->toBe(2)
        ->and($stats['received'])->toBe(1)
        ->and($stats['received_pct'])->toBe(50);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('1 of 2 forms received', false)
        ->assertSee('50%', false)
        ->assertDontSee('0% verified', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Monthly forms rate', false)
        ->assertSee('1/2 monthly forms in', false)
        ->assertSee('50.0%', false)
        ->assertDontSee('Compliance rate', false);
});

test('compliance and reports agree on monthly forms when a location filter is active', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $location = \App\Models\Location::create(['name' => 'Detroit', 'state' => 'MI']);

    $clientInLocation = $this->createClient($org->id, [
        'first_name' => 'Loc',
        'last_name' => 'In',
        'location_id' => $location->id,
    ]);
    $this->createClient($org->id, [
        'first_name' => 'Loc',
        'last_name' => 'Out',
        'location_id' => null,
    ]);

    \App\Models\ComplianceForm::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'client_id' => $clientInLocation->id,
        'period' => now()->format('Y-m'),
        'status' => \App\Models\ComplianceForm::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $session = ['selected_location_id' => $location->id];

    $this->actingAsWithTwoFactor($admin)
        ->withSession($session)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('1 of 2 forms received', false)
        ->assertSee('50%', false);

    $this->actingAsWithTwoFactor($admin)
        ->withSession($session)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('1/2 monthly forms in', false)
        ->assertSee('50.0%', false);
});
