<?php

use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Models\CareDetail;
use App\Services\VisitReportService;

beforeEach(fn () => seedModuleBasics());

test('admin can view visit reports page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('visit-reports'))
        ->assertOk()
        ->assertSee('Visit Reports')
        ->assertSee('EVV proof');
});

test('clocked in visit shows in progress on visit reports', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'actual_clock_in' => now()->subHour(),
        'date' => today()->toDateString(),
        'start_at' => now()->subHours(2),
        'end_at' => now()->addHour(),
        'end_time' => now()->addHour()->format('H:i:s'),
    ]);

    $service = app(VisitReportService::class);
    expect($service->resolveReportStatus($schedule))->toBe(VisitReportService::STATUS_IN_PROGRESS);
});

test('missing clock out flags needs review after visit window', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'actual_clock_in' => now()->subHours(5),
        'date' => today()->toDateString(),
        'start_at' => now()->subHours(5),
        'end_at' => now()->subHours(3),
    ]);

    $service = app(VisitReportService::class);
    expect($service->resolveReportStatus($schedule))->toBe(VisitReportService::STATUS_NEEDS_REVIEW);
});

test('mark missed creates follow up task', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('visit-reports.mark-missed', $schedule->id))
        ->assertRedirect();

    expect(Schedule::find($schedule->id)->status)->toBe(Schedule::STATUS_MISSED);
    expect(Task::where('related_id', $schedule->id)->exists())->toBeTrue();
});

test('approving time correction updates clock out and marks billable', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'start_at' => today()->format('Y-m-d').' 09:00:00',
        'end_at' => today()->format('Y-m-d').' 11:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    $proposed = today()->format('Y-m-d').' 11:00:00';
    $service->proposeTimeCorrection($org->id, $schedule->id, $admin, 'actual_clock_out', $proposed, 'Forgot clock-out');

    $detail = $service->approveTimeCorrection($org->id, $schedule->id, $admin);

    expect($detail['status'])->toBe(VisitReportService::STATUS_COMPLETE);
    expect($detail['billable'])->toBeTrue();
    expect(Schedule::find($schedule->id)->actual_clock_out)->not->toBeNull();
});

test('clean clock out marks visit billable and stamps home coords from client', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'start_at' => today()->format('Y-m-d').' 09:00:00',
        'end_at' => today()->format('Y-m-d').' 11:00:00',
    ]);

    $clock = app(\App\Services\ScheduleClockService::class);

    \Illuminate\Support\Carbon::setTestNow(today()->setTime(9, 0));
    $clock->clockIn($schedule->fresh('client'), 42.3314, -83.0458);

    \Illuminate\Support\Carbon::setTestNow(today()->setTime(11, 0));
    $out = $clock->clockOut($schedule->fresh(['client', 'employee']), 'All good', 42.3314, -83.0458);

    \Illuminate\Support\Carbon::setTestNow();

    expect(data_get($out->metadata, 'billable'))->toBeTrue();
    expect(data_get($out->metadata, 'client_home_lat'))->toBe(42.3314);
    expect(app(\App\Services\VisitReportService::class)->isBillable($out))->toBeTrue();
});

test('visit detail includes care tasks and map points', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'actual_clock_in' => now()->subHours(2),
        'actual_clock_out' => now()->subHour(),
        'total_hours' => 1,
        'evv_status' => true,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    \App\Models\VisitTask::create([
        'organization_id' => $org->id,
        'schedule_id' => $schedule->id,
        'client_id' => $client->id,
        'label' => 'Assist with bathing',
        'is_completed' => true,
        'sort_order' => 1,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('visit-reports.show', $schedule->id))
        ->assertOk()
        ->assertJsonPath('visit.care_tasks.0.label', 'Assist with bathing')
        ->assertJsonPath('visit.care_tasks.0.completed', true)
        ->assertJson(fn ($json) => $json->has('visit.map_points')->etc());
});

test('evv monitor marks overdue scheduled visits as missed', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
        'start_at' => now()->subHours(4),
        'end_at' => now()->subHours(2),
        'end_time' => now()->subHours(2)->format('H:i:s'),
    ]);

    $result = app(\App\Services\EvvMonitorService::class)->run($org->id);

    expect($result['missed'])->toBeGreaterThanOrEqual(1);
    expect(Schedule::find($schedule->id)->status)->toBe(Schedule::STATUS_MISSED);
    expect(Task::where('related_id', $schedule->id)->where('source', Task::SOURCE_SYSTEM)->exists())->toBeTrue();
});

test('clean billable visit increments authorization units_used', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $auth = CareDetail::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'status' => 'Active',
        'total_units' => 100,
        'units_used' => 0,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'start_at' => today()->format('Y-m-d').' 09:00:00',
        'end_at' => today()->format('Y-m-d').' 11:00:00',
    ]);

    $clock = app(\App\Services\ScheduleClockService::class);
    \Illuminate\Support\Carbon::setTestNow(today()->setTime(9, 0));
    $clock->clockIn($schedule->fresh('client'), 42.3314, -83.0458);
    \Illuminate\Support\Carbon::setTestNow(today()->setTime(11, 0));
    $out = $clock->clockOut($schedule->fresh(['client', 'employee']), null, 42.3314, -83.0458);
    \Illuminate\Support\Carbon::setTestNow();

    expect(data_get($out->metadata, 'billable'))->toBeTrue();
    expect((int) $auth->fresh()->units_used)->toBeGreaterThan(0);
    expect(app(VisitReportService::class)->remainingAuthorizationUnits($client->id))
        ->toBe(100 - (int) $auth->fresh()->units_used);
});

test('visits today counter filters to today when clicked', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
        'start_at' => now(),
    ]);
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->subDays(3)->toDateString(),
        'start_at' => now()->subDays(3),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('visit-reports', ['date_preset' => 'today']))
        ->assertOk()
        ->assertSee('Visits today');

    $page = app(VisitReportService::class)->pageData($org->id, request()->merge([
        'date_preset' => 'today',
    ]));

    $todayCounter = collect($page['counters'])->firstWhere('key', 'today');
    expect($todayCounter['date_preset'] ?? null)->toBe('today');
    expect($todayCounter['value'])->toBe(count($page['rows']));
});

test('far clock-out also flags location mismatch', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'actual_clock_in' => now()->subHours(2),
        'actual_clock_out' => now()->subHour(),
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 43.5,
        'clock_out_longitude' => -84.5,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    expect(app(VisitReportService::class)->locationMatches($schedule))->toBeFalse();
});

test('approving location override preserves gps and can mark billable', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 43.5,
        'clock_out_longitude' => -84.5,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    expect($service->locationMatches($schedule))->toBeFalse();
    expect($service->isBillable($schedule))->toBeFalse();

    $detail = $service->approveLocationOverride(
        $org->id,
        $schedule->id,
        $admin,
        'Client was at adult day program with caregiver',
    );

    $fresh = Schedule::find($schedule->id);

    expect((float) $fresh->clock_out_latitude)->toBe(43.5);
    expect((float) $fresh->clock_out_longitude)->toBe(-84.5);
    expect($service->rawLocationMatches($fresh))->toBeFalse();
    expect($service->locationMatches($fresh))->toBeTrue();
    expect($service->hasApprovedLocationOverride($fresh))->toBeTrue();
    expect($detail['location_match'])->toBe('Yes (Approved Override)');
    expect($detail['billable'])->toBeTrue();
    expect($detail['location_overrides'])->not->toBeEmpty();
    expect($detail['location_overrides'][0]['reason'])->toContain('adult day program');
    expect($detail['location_overrides'][0]['immutable'])->toBeTrue();
    expect($detail['location_overrides'][0]['entry_hash'])->not->toBeEmpty();
    expect($detail['location_overrides'][0]['original_clock_out']['lat'])->toBe(43.5);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('visit-reports.approve-location', $schedule->id), [
            'reason' => 'Already approved',
        ])
        ->assertStatus(422);
});

test('cannot approve location when there is no mismatch', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('visit-reports.approve-location', $schedule->id), [
            'reason' => 'Should not work',
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'This visit does not have a location mismatch to approve.']);
});

test('unauthorized user cannot approve location override', function () {
    $org = $this->createOrganization();
    $role = \App\Models\Role::where('slug', 'administrator')->firstOrFail();
    $manageId = \App\Models\Permission::where('slug', 'manage_visit_reports')->value('id');
    $role->permissions()->detach($manageId);

    $viewer = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    expect($viewer->hasPermission('view_visit_reports'))->toBeTrue();
    expect($viewer->hasPermission('manage_visit_reports'))->toBeFalse();

    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 43.5,
        'clock_out_longitude' => -84.5,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $this->actingAsWithTwoFactor($viewer)
        ->get(route('visit-reports'))
        ->assertOk();

    $detail = app(VisitReportService::class)->detail($org->id, $schedule->id, $viewer);
    expect($detail['can_approve_location'])->toBeFalse();
    expect($detail['can_fix'])->toBeFalse();

    $this->actingAsWithTwoFactor($viewer)
        ->postJson(route('visit-reports.approve-location', $schedule->id), [
            'reason' => 'Unauthorized attempt',
        ])
        ->assertForbidden();
});

test('location override audit history is immutable', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 43.5,
        'clock_out_longitude' => -84.5,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    $detail = $service->approveLocationOverride($org->id, $schedule->id, $admin, 'Day program');
    $hash = $detail['location_overrides'][0]['entry_hash'];

    $tampered = $detail['location_overrides'];
    $tampered[0]['reason'] = 'Silently rewritten';

    expect(fn () => $service->assertLocationOverridesImmutable(Schedule::find($schedule->id), $tampered))
        ->toThrow(\RuntimeException::class, 'immutable');

    expect(fn () => $service->assertLocationOverridesImmutable(Schedule::find($schedule->id), []))
        ->toThrow(\RuntimeException::class, 'cannot be removed');

    // Service metadata writes preserve sealed audits.
    $service->proposeTimeCorrection(
        $org->id,
        $schedule->id,
        $admin,
        'actual_clock_out',
        today()->format('Y-m-d').' 10:05:00',
        'Minor time tweak',
    );
    $fresh = Schedule::find($schedule->id);
    expect(data_get($fresh->metadata, 'location_overrides.0.entry_hash'))->toBe($hash);
    expect(data_get($fresh->metadata, 'location_overrides.0.reason'))->toBe('Day program');
});

test('listing shows approved override location label', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
        'first_name' => 'Override',
        'last_name' => 'Client',
    ]);
    $caregiver = $this->createEmployee($org->id, ['first_name' => 'Override', 'last_name' => 'Care']);
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 43.5,
        'clock_out_longitude' => -84.5,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    app(VisitReportService::class)->approveLocationOverride(
        $org->id,
        $schedule->id,
        $admin,
        'Approved for listing label',
    );

    $this->actingAsWithTwoFactor($admin)
        ->get(route('visit-reports'))
        ->assertOk()
        ->assertSee('Yes (Approved Override)');
});

test('far clock-in flags location mismatch and needs review', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'date' => today()->toDateString(),
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'actual_clock_in' => now()->subHour(),
        'clock_in_latitude' => 43.5,
        'clock_in_longitude' => -84.5,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    expect($service->locationMatches($schedule))->toBeFalse();
    expect($service->resolveReportStatus($schedule))->toBe(VisitReportService::STATUS_NEEDS_REVIEW);
    expect($service->isBillable($schedule))->toBeFalse();
});

test('manual time edit keeps original evv and requires approval', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $originalOut = today()->format('Y-m-d').' 10:00:00';
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => $originalOut,
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    $proposed = today()->format('Y-m-d').' 11:00:00';
    $detail = $service->proposeTimeCorrection(
        $org->id,
        $schedule->id,
        $admin,
        'actual_clock_out',
        $proposed,
        'Caregiver forgot correct end time',
    );

    expect($detail['status'])->toBe(VisitReportService::STATUS_NEEDS_REVIEW);
    expect($detail['billable'])->toBeFalse();
    expect($detail['original_evv']['actual_clock_out'])->toContain('10:00');
    expect($detail['time_corrections'][0]['original'])->toContain('10:00');
    expect($detail['time_corrections'][0]['approved'])->toBeFalse();
    expect($detail['time_corrections'][0]['by_user_name'])->toBe($admin->name);
    expect($detail['time_corrections'][0]['reason'])->toContain('forgot');

    expect(\App\Models\WorkflowQueueItem::where('slug', 'evv-review-'.$schedule->id)->exists())->toBeTrue();

    // Clock field unchanged until approval.
    expect(Schedule::find($schedule->id)->actual_clock_out->format('Y-m-d H:i:s'))
        ->toBe(\Illuminate\Support\Carbon::parse($originalOut)->format('Y-m-d H:i:s'));

    $approved = $service->approveTimeCorrection($org->id, $schedule->id, $admin);
    $fresh = Schedule::find($schedule->id);

    expect($approved['status'])->toBe(VisitReportService::STATUS_COMPLETE);
    expect(data_get($fresh->metadata, 'original_evv.actual_clock_out'))->toContain('10:00');
    expect(data_get($fresh->metadata, 'time_corrections.0.original'))->toContain('10:00');
    expect(data_get($fresh->metadata, 'time_corrections.0.approved'))->toBeTrue();
    expect(\App\Models\WorkflowQueueItem::where('slug', 'evv-review-'.$schedule->id)->value('status'))
        ->toBe(\App\Models\WorkflowQueueItem::STATUS_COMPLETED);
});

test('evv monitor auto-marks clean visits billable and queues problem visits', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    $clean = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'evv_status' => true,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $stuck = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'date' => today()->toDateString(),
        'start_at' => now()->subHours(5),
        'end_at' => now()->subHours(3),
        'actual_clock_in' => now()->subHours(5),
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $result = app(\App\Services\EvvMonitorService::class)->run($org->id);

    expect($result['billable'])->toBeGreaterThanOrEqual(1);
    expect(data_get(Schedule::find($clean->id)->metadata, 'billable'))->toBeTrue();
    expect(\App\Models\WorkflowQueueItem::where('slug', 'evv-review-'.$stuck->id)->exists())->toBeTrue();
});

test('mark missed pushes workflow queue human task', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Anaya', 'last_name' => 'Client']);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('visit-reports.mark-missed', $schedule->id))
        ->assertRedirect();

    $item = \App\Models\WorkflowQueueItem::where('slug', 'evv-missed-'.$schedule->id)->first();
    expect($item)->not->toBeNull();
    expect($item->queue_type)->toBe(\App\Models\WorkflowQueueItem::TYPE_HUMAN_TASK);
    expect($item->status)->toBe(\App\Models\WorkflowQueueItem::STATUS_PENDING);
    expect($item->meta['title'] ?? '')->toContain('Anaya');
});

test('completed counter equals filtered row count', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
    ]);
    $caregiver = $this->createEmployee($org->id);

    foreach (range(1, 3) as $i) {
        $this->createSchedule($org->id, $client->id, $caregiver->id, [
            'status' => Schedule::STATUS_COMPLETED,
            'date' => today()->toDateString(),
            'start_time' => sprintf('%02d:00:00', 8 + $i),
            'end_time' => sprintf('%02d:00:00', 9 + $i),
            'start_at' => today()->setTime(8 + $i, 0),
            'end_at' => today()->setTime(9 + $i, 0),
            'actual_clock_in' => today()->setTime(8 + $i, 0),
            'actual_clock_out' => today()->setTime(9 + $i, 0),
            'total_hours' => 1,
            'evv_status' => true,
            'clock_in_latitude' => 42.3314,
            'clock_in_longitude' => -83.0458,
            'clock_out_latitude' => 42.3314,
            'clock_out_longitude' => -83.0458,
            'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
        ]);
    }

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_MISSED,
        'date' => today()->toDateString(),
        'start_at' => now(),
    ]);

    $page = app(VisitReportService::class)->pageData($org->id, request()->merge([
        'date_preset' => 'today',
        'report_status' => VisitReportService::STATUS_COMPLETE,
    ]));

    $completeCounter = collect($page['counters'])->firstWhere('key', 'complete');
    expect($completeCounter['value'])->toBe(count($page['rows']));
    expect(count($page['rows']))->toBe(3);
});
