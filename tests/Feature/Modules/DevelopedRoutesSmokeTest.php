<?php

use App\Models\BillingClaimAudit;
use App\Models\CaregiverAssignment;
use App\Models\Contact;
use App\Models\Location;
use App\Models\PayRecord;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

/**
 * Smoke-test every developed module page: must not 500, and must not be a bare 404.
 */
test('developed module index pages load for admin', function (string $routeName) {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route($routeName))
        ->assertSuccessful();
})->with([
    'dashboard' => 'dashboard',
    'clients index' => 'clients.index',
    'clients create' => 'clients.create',
    'caregivers' => 'caregivers',
    'caregivers create' => 'caregivers.create',
    'intakes' => 'intakes.index',
    'schedule' => 'schedule.index',
    'billing claims audit' => 'billing-claims-audit.index',
    'payroll' => 'payroll',
    'compliance' => 'compliance',
    'audit view' => 'audit-view',
    'directory' => 'directory',
    'staff' => 'staff.index',
    'messages' => 'messages.index',
    'communications' => 'communications.index',
    'reports' => 'reports.index',
    'workflow queues' => 'workflow-queues',
    'visit reports' => 'visit-reports',
    'tasks' => 'tasks',
    'forms' => 'forms',
    'data exploration' => 'data-exploration',
    'profile' => 'profile',
    'settings' => 'settings.index',
    'communications efax compose' => 'communications.index',
    'employees' => 'employees.index',
    'request templates' => 'request-templates.index',
]);

test('super admin settings pages load', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.index'))
        ->assertOk();

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global'))
        ->assertOk();
});

test('developed show pages load for admin with real records', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Smoke Loc', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'location_id' => $location->id]);
    $intake = createTestIntake($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id);
    $contact = $this->createContact($org->id);
    $claim = billingClaimAuditRecord($org->id, $client->id);
    $pay = payrollTestRecord($org->id, $caregiver->id, $client->id);

    $session = ['selected_location_id' => $location->id];
    $actor = fn () => $this->actingAsWithTwoFactor($admin)->withSession($session);

    $actor()->get(route('clients.show', $client->id))->assertOk();
    $actor()->get(route('caregivers.show', $caregiver->id))->assertOk();
    $actor()->get(route('intakes.show', $intake->id))->assertOk();
    $actor()->get(route('schedule.show', $schedule->id))->assertOk();
    $actor()->get(route('directory.show', $contact->id))->assertOk();
    $actor()->get(route('billing-claims-audit.show', $claim->id))->assertOk();
    $actor()->get(route('payroll.show', $pay->id))->assertOk();
});

test('reports visit legacy path redirects to visit reports', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.visit'))
        ->assertRedirect(route('visit-reports'));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('visit-reports'))
        ->assertOk()
        ->assertSee('Visit Reports');
});

test('placeholder module routes render coming soon without 404', function (string $routeName) {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Coming soon');
})->with([
    'marketing' => 'marketing',
]);

test('retired billing module returns 404 not broken page', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing.index'))
        ->assertNotFound();
});

test('calendar route redirects to schedule without error', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('calendar'))
        ->assertRedirect(route('schedule.index', ['view' => 'month']));
});

test('contacts legacy route redirects to directory', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get('/contacts')
        ->assertRedirect(route('directory'));
});

test('named routes used in dashboard resolve without exception', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $routes = [
        route('intakes.index'),
        route('clients.create'),
        route('workflow-queues'),
        route('billing-claims-audit.index'),
        route('schedule.index'),
    ];

    foreach ($routes as $url) {
        $this->actingAsWithTwoFactor($admin)->get($url)->assertSuccessful();
    }

    $this->actingAsWithTwoFactor($admin)
        ->get(route('efax.compose'))
        ->assertRedirect(route('communications.index', ['compose' => 'efax']));
});

test('client schedule tab shows linked events', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $this->createSchedule($org->id, $client->id, $caregiver->id, ['title' => 'Linked Visit']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'schedule']))
        ->assertOk()
        ->assertSee('Linked Visit');
});

test('caregiver assignment relationship is queryable from both sides', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    CaregiverAssignment::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $caregiver->id,
        'status' => 'Active',
    ]);

    expect($client->caregiverAssignments()->where('status', 'Active')->exists())->toBeTrue()
        ->and($caregiver->assignments()->where('client_id', $client->id)->exists())->toBeTrue();
});
