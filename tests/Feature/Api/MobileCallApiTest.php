<?php

use App\Models\CallLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = $this->createOrganization();
    $this->user = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    $this->employee = $this->createEmployee($this->org->id, [
        'user_id' => $this->user->id,
        'first_name' => 'Robert',
        'last_name' => 'Nguyen',
        'phone' => '(313) 555-2001',
    ]);
    $this->client = $this->createClient($this->org->id, [
        'first_name' => 'Maria',
        'last_name' => 'Hassan',
        'phone' => '(313) 555-0101',
    ]);
    $this->employee->clients()->attach($this->client->id);
});

test('placing a call requires authentication', function () {
    $this->postJson('/api/calls', ['client_id' => $this->client->id])->assertUnauthorized();
});

test('calling an assigned client with RingCentral off returns a manual tel fallback and logs it', function () {
    // No RingCentral config in the test env -> the call degrades to manual and
    // the app dials the returned tel: link natively.
    Sanctum::actingAs($this->user);

    $this->postJson('/api/calls', ['client_id' => $this->client->id])
        ->assertCreated()
        ->assertJsonPath('data.mode', 'manual')
        ->assertJsonPath('data.status', 'manual')
        ->assertJsonPath('data.client_name', 'Maria Hassan')
        ->assertJsonPath('data.tel', 'tel:3135550101');

    $this->assertDatabaseHas('call_logs', [
        'employee_id' => $this->employee->id,
        'client_id' => $this->client->id,
        'mode' => 'manual',
    ]);
});

test('calling an assigned client bridges via RingCentral RingOut when configured', function () {
    config([
        'ringcentral.client_id' => 'test-id',
        'ringcentral.client_secret' => 'test-secret',
        'ringcentral.from_number' => '+13135550000',
    ]);

    Http::fake([
        '*/restapi/oauth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600, 'scope' => 'RingOut'], 200),
        '*/restapi/v1.0/account/~/extension/~/ring-out' => Http::response([
            'id' => 'rc-call-123',
            'status' => ['callStatus' => 'InProgress'],
        ], 200),
    ]);

    Sanctum::actingAs($this->user);

    $this->postJson('/api/calls', ['client_id' => $this->client->id])
        ->assertCreated()
        ->assertJsonPath('data.mode', 'ringout')
        ->assertJsonPath('data.status', 'initiated')
        ->assertJsonPath('data.provider', 'ringcentral')
        ->assertJsonPath('data.provider_call_id', 'rc-call-123');

    $this->assertDatabaseHas('call_logs', [
        'client_id' => $this->client->id,
        'mode' => 'ringout',
        'provider_call_id' => 'rc-call-123',
    ]);
});

test('a caregiver cannot call a client they are not assigned to', function () {
    Sanctum::actingAs($this->user);

    $stranger = $this->createClient($this->org->id, ['first_name' => 'Stranger', 'phone' => '(313) 555-9999']);

    $this->postJson('/api/calls', ['client_id' => $stranger->id])->assertForbidden();

    $this->assertDatabaseCount('call_logs', 0);
});

test('calling a client with no phone number returns 422', function () {
    Sanctum::actingAs($this->user);

    $noPhone = $this->createClient($this->org->id, ['first_name' => 'NoPhone', 'phone' => null]);
    $this->employee->clients()->attach($noPhone->id);

    $this->postJson('/api/calls', ['client_id' => $noPhone->id])->assertStatus(422);
});

test('call history lists only the caregivers own calls', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/calls', ['client_id' => $this->client->id])->assertCreated();

    // Another caregiver's call must not leak into this caregiver's history.
    $other = $this->createEmployee($this->org->id, ['first_name' => 'Other']);
    CallLog::create([
        'organization_id' => $this->org->id,
        'employee_id' => $other->id,
        'client_id' => $this->client->id,
        'client_name' => 'Maria Hassan',
        'mode' => 'manual',
        'status' => 'manual',
        'to_number' => '(313) 555-0101',
    ]);

    $this->getJson('/api/calls')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.client_name', 'Maria Hassan');
});
