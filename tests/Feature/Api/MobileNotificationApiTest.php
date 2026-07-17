<?php

use App\Models\CommunicationNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = $this->createOrganization();
    $this->user = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    $this->createEmployee($this->org->id, ['user_id' => $this->user->id]);
});

function makeNotification(int $orgId, int $userId, array $attrs = []): CommunicationNotification
{
    $createdAt = $attrs['created_at'] ?? null;
    unset($attrs['created_at']);

    $notification = CommunicationNotification::create(array_merge([
        'organization_id' => $orgId,
        'user_id'         => $userId,
        'type'            => CommunicationNotification::TYPE_SECURE_MESSAGE,
        'title'           => 'Schedule Updated',
        'body'            => 'New Visit Added: Robert Lee — Thursday 6:00 PM.',
    ], $attrs));

    if ($createdAt !== null) {
        $notification->forceFill(['created_at' => $createdAt])->save();
    }

    return $notification;
}

test('notifications require authentication', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

test('lists the logged-in users notifications newest first', function () {
    Sanctum::actingAs($this->user);

    makeNotification($this->org->id, $this->user->id, ['title' => 'Older', 'created_at' => now()->subDay()]);
    makeNotification($this->org->id, $this->user->id, ['title' => 'Newer']);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Newer')
        ->assertJsonStructure(['data' => [['id', 'type', 'title', 'body', 'read', 'created_at', 'time_ago']]]);
});

test('does not leak notifications from other users', function () {
    Sanctum::actingAs($this->user);

    $other = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    makeNotification($this->org->id, $other->id);

    $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data');
});

test('unread count reflects unread notifications', function () {
    Sanctum::actingAs($this->user);

    makeNotification($this->org->id, $this->user->id);
    makeNotification($this->org->id, $this->user->id, ['read_at' => now()]);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 1);
});

test('can mark a notification read', function () {
    Sanctum::actingAs($this->user);

    $n = makeNotification($this->org->id, $this->user->id);

    $this->postJson("/api/notifications/{$n->id}/read")->assertOk();

    expect($n->fresh()->read_at)->not->toBeNull();
});

test('can filter to unread only', function () {
    Sanctum::actingAs($this->user);

    makeNotification($this->org->id, $this->user->id, ['title' => 'Unread one']);
    makeNotification($this->org->id, $this->user->id, ['read_at' => now()]);

    $this->getJson('/api/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Unread one');
});

test('can mark all notifications read', function () {
    Sanctum::actingAs($this->user);

    makeNotification($this->org->id, $this->user->id);
    makeNotification($this->org->id, $this->user->id);

    $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('updated', 2);
    $this->getJson('/api/notifications/unread-count')->assertJsonPath('count', 0);
});

test('can delete a notification', function () {
    Sanctum::actingAs($this->user);

    $n = makeNotification($this->org->id, $this->user->id);

    $this->deleteJson("/api/notifications/{$n->id}")->assertOk();

    expect(CommunicationNotification::find($n->id))->toBeNull();
});

test('cannot mark another users notification read', function () {
    Sanctum::actingAs($this->user);

    $other = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    $n = makeNotification($this->org->id, $other->id);

    $this->postJson("/api/notifications/{$n->id}/read")->assertForbidden();
});
