<?php

use App\Models\Billing;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\LookupTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(LookupTableSeeder::class);
});

test('authenticated admin can access dashboard', function () {
    $org = Organization::create([
        'name' => 'Test Org',
        'address' => '123 Main St, Detroit MI',
        'status' => 'Active',
    ]);

    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Active Clients')
        ->assertSee('Recent Activity');
});

test('guest is redirected from dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('signin'));
});

test('dashboard renders dynamic organization metrics instead of hardcoded totals', function () {
    $org = Organization::create([
        'name' => 'Beydoun Home Care',
        'address' => '123 Michigan Ave, Detroit MI',
        'status' => 'Active',
    ]);

    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'status' => 'Active',
        'email' => 'john@example.com',
    ]);

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => Client::withoutGlobalScopes()->first()->id,
        'invoice_number' => 'INV-1001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 2500,
        'status' => 'Paid',
    ]);

    $response = $this->actingAsWithTwoFactor($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Active Clients')
        ->assertSee('1')
        ->assertDontSee('1,284')
        ->assertDontSee('$284k')
        ->assertDontSee('Xendaar Solution');
});

test('dashboard shows empty activity state when no logs exist', function () {
    $org = Organization::create([
        'name' => 'Empty Org',
        'address' => '1 Test Lane',
        'status' => 'Active',
    ]);

    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No recent activity yet');
});
