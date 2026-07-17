<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\LookupTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(LookupTableSeeder::class);
});

test('client show page renders dynamic client data', function () {
    $org = Organization::create([
        'name' => 'Beydoun Home Care',
        'address' => '123 Michigan Ave, Detroit MI',
        'status' => 'Active',
    ]);

    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $client = Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane.smith@mail.com',
        'phone' => '(313) 555-1002',
        'address' => '456 Oak Ave, Warren MI',
        'member_id' => 'MD-100002',
        'status' => 'Active',
        'county' => 'Macomb',
    ]);

    $this->actingAsWithTwoFactor($user)
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->assertSee('Jane Smith')
        ->assertSee('jane.smith@mail.com')
        ->assertSee('456 Oak Ave, Warren MI')
        ->assertSee('MD-100002')
        ->assertDontSee('Dummy address')
        ->assertDontSee('dummyemail@gmail.com')
        ->assertDontSee('1,284');
});

test('client show page shows empty states for missing optional data', function () {
    $org = Organization::create([
        'name' => 'Beydoun Home Care',
        'status' => 'Active',
    ]);

    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $client = Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Minimal',
        'last_name' => 'Client',
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($user)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'documents']))
        ->assertOk()
        ->assertSee('Minimal Client')
        ->assertSee('New files are auto-classified');

    $this->actingAsWithTwoFactor($user)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'billing']))
        ->assertOk()
        ->assertSee('No billing records yet.');
});

test('client show renders safely with no related records', function () {
    $org = Organization::create(['name' => 'Test Org', 'status' => 'Active']);
    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $client = Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Schedule',
        'last_name' => 'Client',
        'status' => 'Active',
    ]);

    // No authorization, caregiver, contacts, documents or billing — every tab
    // must still render without errors (null-safe display accessors).
    $this->actingAsWithTwoFactor($user)
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->assertSee('Schedule Client')
        ->assertSee('No caregiver assigned yet');
});
