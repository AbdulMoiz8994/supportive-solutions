<?php

use App\Models\Client;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── Credential / compliance expiry flagging ────────────────────────────────

test('ID expiry status flags expired, expiring soon, and valid', function () {
    $org = $this->createOrganization();

    expect($this->createEmployee($org->id, ['id_expiry_date' => now()->subDay()])->id_expiry_status)->toBe('Expired');
    expect($this->createEmployee($org->id, ['id_expiry_date' => now()->addDays(20)])->id_expiry_status)->toBe('Expiring Soon');
    expect($this->createEmployee($org->id, ['id_expiry_date' => now()->addDays(200)])->id_expiry_status)->toBe('Valid');
    expect($this->createEmployee($org->id, ['id_expiry_date' => null])->id_expiry_status)->toBe('Unknown');
});

test('CHAMPS association gates collecting hours and raises an alert', function () {
    $org = $this->createOrganization();

    $notAssociated = $this->createEmployee($org->id, ['champs_association_date' => null]);
    expect($notAssociated->is_champs_associated)->toBeFalse();
    expect(collect($notAssociated->credential_alerts)->pluck('label'))
        ->toContain('Not CHAMPS-associated — cannot collect hours');

    $associated = $this->createEmployee($org->id, ['champs_association_date' => now()]);
    expect($associated->is_champs_associated)->toBeTrue();
});

test('credential alerts include an expired ID and an incomplete background check', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'id_expiry_date' => now()->subDays(5),
        'champs_association_date' => now(),
        'has_background_check' => false,
    ]);

    $labels = collect($employee->credential_alerts)->pluck('label');
    expect($labels)->toContain('ID expired');
    expect($labels)->toContain('Background check incomplete');
});

test('a fully compliant caregiver has no credential alerts', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'id_expiry_date' => now()->addYear(),
        'champs_association_date' => now()->subMonth(),
        'has_background_check' => true,
    ]);

    expect($employee->credential_alerts)->toBe([]);
});

// ─── Hours rollup across assigned clients ───────────────────────────────────

test('weekly and daily hours roll up across every assigned client', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id);

    $c1 = $this->createClient($org->id);
    $c2 = $this->createClient($org->id);
    foreach ([['c' => $c1, 'u' => 56, 'h' => 14], ['c' => $c2, 'u' => 40, 'h' => 10]] as $row) {
        $row['c']->careDetails()->create([
            'billing_code' => 'T1019', 'total_units' => $row['u'], 'hours_per_week' => $row['h'],
            'start_date' => now(), 'end_date' => now()->addMonths(6), 'status' => 'Active',
            'organization_id' => $org->id,
        ]);
    }
    $employee->clients()->attach([$c1->id, $c2->id]);
    $employee->load('clients.careDetails');

    expect($employee->total_weekly_hours)->toBe(24.0); // 14 + 10
    expect($employee->total_daily_hours)->toBe(round(24 / 7, 2));
    expect($employee->assigned_client_count)->toBe(2);
});

test('a caregiver with no assignments rolls up to zero hours', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id);

    expect($employee->total_weekly_hours)->toBe(0.0);
    expect($employee->assigned_client_count)->toBe(0);
});
