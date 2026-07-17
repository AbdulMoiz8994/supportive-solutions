<?php

use App\Models\CareDetail;
use App\Models\CaregiverAuditLog;
use App\Models\Intake;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('clients export returns csv for authorized user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'Export', 'last_name' => 'Client', 'member_id' => 'M123']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();
});

test('caregivers export returns csv for authorized user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'first_name' => 'Care', 'last_name' => 'Giver']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();
});

test('client pa letter download generates html when no uploaded document exists', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(5),
        'total_units' => 400,
        'hours_per_week' => 25,
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.pa-letter.download', $client->id))
        ->assertOk()
        ->assertDownload();
});

test('client authorizations export returns csv', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(5),
        'total_units' => 400,
        'hours_per_week' => 25,
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.authorizations.export', $client->id))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();
});

test('client documents download all returns zip when files exist', function () {
    Storage::fake('local');
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Storage::disk('local')->put('documents/test-client.pdf', 'pdf-content');
    $this->createDocument($org->id, $client, [
        'path' => 'documents/test-client.pdf',
        'category' => 'Compliance',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.documents.download-all', $client->id))
        ->assertOk()
        ->assertDownload();
});

test('caregiver audit csv export returns csv', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    CaregiverAuditLog::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'actor_name' => 'Admin User',
        'actor_role' => 'Administrator',
        'actor_type' => 'human',
        'action' => 'Field edited',
        'entity' => 'Pay & Payroll',
        'value_before' => '$15.00 / hr',
        'value_after' => '$16.00 / hr',
        'source' => 'App (web)',
        'occurred_at' => now(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.audit.export', $caregiver->id))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();
});

test('caregiver audit pdf export returns downloadable html', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.audit.export-pdf', $caregiver->id))
        ->assertOk()
        ->assertDownload();
});

test('intake assessment download returns html file', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = Intake::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Lead',
        'last_name' => 'Person',
        'status' => 'New',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('intakes.download', $intake->id))
        ->assertOk()
        ->assertDownload();
});
