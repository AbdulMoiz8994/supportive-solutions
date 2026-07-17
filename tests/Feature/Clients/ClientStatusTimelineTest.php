<?php

use App\Models\Client;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a client stuck in a waiting status beyond the window needs attention', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Pending']);
    $client->statusHistories()->create([
        'from_status' => 'New', 'to_status' => 'Pending', 'effective_date' => now()->subDays(50),
    ]);
    $client->load('statusHistories');

    expect($client->current_status_name)->toBe('Pending');
    expect($client->days_in_current_status)->toBeGreaterThanOrEqual(50);
    expect($client->status_needs_attention)->toBeTrue();
});

test('a client recently moved into a waiting status does not need attention', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Pending']);
    $client->statusHistories()->create(['to_status' => 'Pending', 'effective_date' => now()->subDays(10)]);
    $client->load('statusHistories');

    expect($client->status_needs_attention)->toBeFalse();
});

test('an active client never needs status attention even after a long time', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Active']);
    $client->statusHistories()->create(['to_status' => 'Active', 'effective_date' => now()->subDays(300)]);
    $client->load('statusHistories');

    expect($client->status_needs_attention)->toBeFalse();
});

test('days in status falls back to created_at when there is no history', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Pending']);

    expect($client->days_in_current_status)->not->toBeNull();
});

test('the waiting-status scope finds only clients in a waiting status', function () {
    $org = $this->createOrganization();
    $this->createClient($org->id, ['status' => 'Pending']);
    $this->createClient($org->id, ['status' => 'On Hold']);
    $this->createClient($org->id, ['status' => 'Active']);

    expect(Client::withoutGlobalScopes()->inWaitingStatus()->count())->toBe(2);
});
