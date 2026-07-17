<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\LookupTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(LookupTableSeeder::class);
});

test('redesigned grouped nav uses clean terminology', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->actingAs($admin);

    // The design-system redesign replaced the flat control/system/compliance
    // lists with a single grouped model (CONTROL / FINANCIAL / ENGAGEMENT / …).
    $groups = collect(\App\Helpers\MenuHelper::getMenuGroups());
    $allMenuNames = $groups->flatMap(fn ($group) => $group['items'])->pluck('name')->all();

    expect($allMenuNames)
        ->toContain('Clients', 'Caregivers', 'Billing & Claims', 'Directory')
        ->not->toContain('Caregiver List', 'Billings', 'Leads', 'Work Shifts');

    // Grouped headers are present and non-empty for an office admin.
    expect($groups->pluck('name')->all())->toContain('CONTROL', 'FINANCIAL');
});

test('caregiver routes still work with employee terminology labels', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $employee = Employee::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Alex',
        'last_name' => 'Rivera',
        'email' => 'alex@example.com',
        'position' => 'Caregiver',
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers'))
        ->assertOk()
        ->assertSee('Caregiver', false)
        ->assertSee('All Caregivers', false)
        ->assertDontSee('All Employees', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.show', $employee->id))
        ->assertOk()
        ->assertSee('Alex Rivera', false)
        ->assertSee('Personal &amp; Employment', false)
        ->assertDontSee('Caregiver Employment Application', false)
        ->assertDontSee('BrightCare Home Health', false);
});

test('client list renders required column labels', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Jamie',
        'last_name' => 'Lee',
        'dob' => '1990-05-10',
        'phone' => '(313) 555-0199',
        'county' => 'Oakland',
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Clients', false)
        ->assertSee('Name', false)
        ->assertSee('County', false)
        ->assertSee('Program', false)
        ->assertSee('Authorization', false)
        ->assertSee('Status', false)
        ->assertSee('Jamie', false);
});

test('client profile tab labels render without asw terminology', function () {
    $org = Organization::create(['name' => 'Home Care Agency', 'status' => 'Active']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $client = Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Taylor',
        'last_name' => 'Morgan',
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->assertSee('Taylor Morgan', false)
        ->assertSee('Demographics', false)
        ->assertSee('Caregiver Assignment', false)
        ->assertSee('Compliance Forms', false)
        ->assertSee('Audit Trail', false)
        ->assertDontSee('Application Status', false)
        // Client review B1 reintroduced ASW on purpose: the demographics tab
        // has a Directory-driven "DHS ASW (Adult Services Worker)" picker.
        ->assertSee('DHS ASW (Adult Services Worker)', false);
});

test('audit view route remains real audit trail after menu cleanup', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('audit-view'))
        ->assertOk()
        ->assertSee('System Activity Stream', false)
        ->assertDontSee('Coming Soon — Placeholder Module', false);
});
