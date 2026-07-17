<?php

use App\Models\Client;
use App\Models\CoverageType;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function securityClientPayload(array $overrides = []): array
{
    $coverageType = CoverageType::query()->first() ?? CoverageType::create(['name' => 'DHS Home Help']);

    return array_merge([
        'first_name' => 'Safe',
        'last_name' => 'Client',
        'coverage_type_id' => $coverageType->id,
    ], $overrides);
}

function xssPayload(): string
{
    return '<script>alert("xss")</script>';
}

test('client store persists xss in name without executing in response', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $payload = xssPayload();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), securityClientPayload([
            'first_name' => $payload,
            'last_name' => 'SafeLast',
            'email' => 'xss@example.com',
        ]))
        ->assertRedirect();

    $client = Client::withoutGlobalScopes()->where('email', 'xss@example.com')->first();
    expect($client)->not->toBeNull();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->assertDontSee('<script>alert("xss")</script>', false);
});

test('sql injection in search query does not break application', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'Safe', 'last_name' => 'Client']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('search.global', ['query' => "' OR 1=1; DROP TABLE clients; --"]))
        ->assertOk();

    expect(Client::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(1);
});

test('unicode characters are accepted in client names', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), securityClientPayload([
            'first_name' => 'José',
            'last_name' => 'Müller',
            'email' => 'unicode@example.com',
        ]))
        ->assertRedirect();

    expect(Client::withoutGlobalScopes()->where('first_name', 'José')->exists())->toBeTrue();
});

test('oversized input is rejected by validation', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), securityClientPayload([
            'first_name' => str_repeat('A', 300),
            'last_name' => 'Test',
        ]))
        ->assertSessionHasErrors('first_name');
});
