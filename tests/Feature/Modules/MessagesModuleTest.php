<?php

use App\Models\Message;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access messages', function () {
    $this->get(route('messages.index'))->assertRedirect(route('signin'));
});

test('admin can view messaging portal', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id, 'name' => 'Colleague User']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertSee('Messaging');
});

test('message store creates conversation record with organization scope', function () {
    $org = $this->createOrganization();
    $sender = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $receiver = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($sender)
        ->post(route('messages.store'), [
            'receiver_id' => $receiver->id,
            'content' => 'Hello from tests',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'organization_id' => $org->id,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'content' => 'Hello from tests',
    ]);
});

test('message store validates required fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('messages.store'), [])
        ->assertSessionHasErrors(['content', 'receiver_id']);
});

test('message store rejects nonexistent receiver', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('messages.store'), [
            'receiver_id' => 999999,
            'content' => 'Ghost message',
        ])
        ->assertSessionHasErrors(['receiver_id']);
});

test('message show displays thread and marks inbound messages read', function () {
    $org = $this->createOrganization();
    $sender = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $receiver = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id, 'name' => 'Thread Peer']);

    Message::create([
        'organization_id' => $org->id,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'content' => 'Unread ping',
    ]);

    $this->actingAsWithTwoFactor($receiver)
        ->get(route('messages.show', $sender->id))
        ->assertOk()
        ->assertSee('Unread ping');

    expect(Message::where('sender_id', $sender->id)->where('receiver_id', $receiver->id)->first()->read_at)->not->toBeNull();
});

test('message show returns 404 for unknown user', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('messages.show', 999999))
        ->assertNotFound();
});
