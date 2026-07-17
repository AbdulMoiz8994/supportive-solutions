<?php

use App\Models\Client;
use App\Models\User;
use App\Policies\ClientPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->policy = new ClientPolicy();
});

test('client policy allows super admin cross organization access', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $client = $this->createClient($orgA->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    expect($this->policy->update($super, Client::withoutGlobalScopes()->find($client->id)))->toBeTrue();
});

test('client policy denies staff without edit permission', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    expect($this->policy->update($staff, Client::withoutGlobalScopes()->find($client->id)))->toBeFalse()
        ->and($this->policy->delete($staff, Client::withoutGlobalScopes()->find($client->id)))->toBeFalse();
});

test('client policy allows admin within same organization', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    expect($this->policy->update($admin, Client::withoutGlobalScopes()->find($client->id)))->toBeTrue();
});

test('client policy denies admin from different organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    expect($this->policy->update($adminB, Client::withoutGlobalScopes()->find($client->id)))->toBeFalse();
});
