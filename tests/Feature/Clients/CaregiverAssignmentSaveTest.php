<?php

use App\Models\CaregiverAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function makeAssignment($org, $client): CaregiverAssignment
{
    $employee = test()->createEmployee($org->id, ['position' => 'Caregiver']);

    return CaregiverAssignment::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'relationship' => 'Family',
        'status' => 'Active',
        'assigned_since' => now()->subMonths(2),
    ]);
}

test('Assignment Details persists relationship and status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $assignment = makeAssignment($org, $client);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.assignment.update', ['id' => $client->id, 'assignment' => $assignment->id]), [
            'section' => 'cg-assignment',
            'tab' => 'caregiver',
            'relationship' => 'Daughter',
            'status' => 'On Hold',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'caregiver']))
        ->assertSessionHas('success', 'Changes saved.');

    $assignment->refresh();
    expect($assignment->relationship)->toBe('Daughter');
    expect($assignment->status)->toBe('On Hold');
});

test('Assignment status is constrained to known values', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $assignment = makeAssignment($org, $client);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.assignment.update', ['id' => $client->id, 'assignment' => $assignment->id]), [
            'status' => 'Bogus',
        ])
        ->assertSessionHasErrors('status');

    expect($assignment->fresh()->status)->toBe('Active');
});

test('Live-In Exemption panel persists status, dates and EVV via clients.update', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'cg-livein',
            'tab' => 'caregiver',
            'live_in_exemption_status' => 'Approved',
            'live_in_exemption_approved_at' => '2026-05-01',
            'live_in_exemption_expires_at' => '2026-11-01',
            'evv_status' => 'Exempt — no clock in / out required',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'caregiver']));

    $client->refresh();
    expect($client->live_in_exemption_status)->toBe('Approved');
    expect($client->evv_status)->toBe('Exempt — no clock in / out required');
    expect($client->live_in_exemption_approved_at?->toDateString())->toBe('2026-05-01');
    expect($client->live_in_exemption_expires_at?->toDateString())->toBe('2026-11-01');
});

test('Pay Eligibility persists the hourly rate', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'cg-pay',
            'tab' => 'caregiver',
            'billing_rate' => 18.5,
        ])->assertRedirect();

    expect((float) $client->fresh()->billing_rate)->toBe(18.5);
});

test('assignment update is blocked across organizations', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization();
    $client = $this->createClient($orgB->id);
    $assignment = makeAssignment($orgB, $client);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->put(route('clients.assignment.update', ['id' => $client->id, 'assignment' => $assignment->id]), [
            'relationship' => 'Should not save',
        ])->assertForbidden();

    expect($assignment->fresh()->relationship)->toBe('Family');
});
