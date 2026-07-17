<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = $this->createOrganization();
    $this->user = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
});

test('realtime config requires authentication', function () {
    $this->getJson('/api/realtime/config')->assertUnauthorized();
});

test('realtime config returns the reverb connection parameters the client must match', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-app-key');
    config()->set('broadcasting.connections.reverb.options', [
        'host' => 'beydountech.com',
        'port' => 443,
        'scheme' => 'https',
    ]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/realtime/config')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('driver', 'reverb')
        ->assertJsonPath('key', 'test-app-key')
        ->assertJsonPath('host', 'beydountech.com')
        ->assertJsonPath('port', 443)
        ->assertJsonPath('scheme', 'https')
        ->assertJsonPath('use_tls', true)
        ->assertJsonPath('event', 'message.sent')
        ->assertJsonPath('channels.user', 'private-user.'.$this->user->id)
        ->assertJsonPath('auth_endpoint', url('/broadcasting/auth'));
});

test('realtime config never leaks the app secret', function () {
    config()->set('broadcasting.connections.reverb.secret', 'super-secret-value');
    Sanctum::actingAs($this->user);

    $body = $this->getJson('/api/realtime/config')->assertOk()->getContent();

    expect($body)->not->toContain('super-secret-value');
    expect($body)->not->toContain('secret');
});

test('realtime config reports disabled when broadcasting is on the log driver', function () {
    config()->set('broadcasting.default', 'log');
    Sanctum::actingAs($this->user);

    $this->getJson('/api/realtime/config')
        ->assertOk()
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('driver', 'log');
});
