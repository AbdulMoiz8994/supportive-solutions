<?php

use App\Models\CaregiverActivationCode;
use App\Models\Employee;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('extended catalog report pages load', function (string $slug) {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.show', $slug))
        ->assertOk();
})->with([
    'payer-mix',
    'visit-completion',
    'forms-timeliness',
    'onboarding-pipeline',
    'escalation-volume',
]);

test('reports export supports xlsx and pdf', function (string $format) {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.export', ['report' => 'payer-mix', 'format' => $format]));

    $response->assertOk();
})->with(['xlsx', 'pdf']);

test('reports view all shows full financial library', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.index', ['category' => 'financial', 'view_all' => 1]))
        ->assertOk()
        ->assertSee('Payer Mix')
        ->assertSee('Cash Position');
});

test('report run records last run timestamp', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.show', 'revenue-collections'))
        ->assertOk();

    expect(ReportRun::where('report_slug', 'revenue-collections')->exists())->toBeTrue();
});

test('super admin can schedule a report', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('reports.schedule.store'), [
            'report_slug' => 'revenue-collections',
            'frequency' => 'monthly',
            'format' => 'csv',
            'recipients' => $super->email,
            'period' => now()->format('Y-m'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ReportSchedule::where('report_slug', 'revenue-collections')->exists())->toBeTrue();
});

test('custom report builder generates preview', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('reports.custom.run'), [
            'prompt' => 'Show me DHS clients in Wayne County',
        ])
        ->assertRedirect();
});

test('super admin can generate activation code', function () {
    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);
    $employee = Employee::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'James',
        'last_name' => 'Miller',
        'position' => 'Caregiver',
        'status' => 'Active',
        'email' => 'james@example.com',
    ]);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.activation-codes.store'), [
            'employee_id' => $employee->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CaregiverActivationCode::where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('super admin can view full audit log page', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global.audit-log'))
        ->assertOk()
        ->assertSee('Audit log');
});
