<?php

use App\Models\User;
use App\Services\Communication\SecureMessageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = $this->createOrganization();

    $this->user = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id, 'name' => 'Robert Nguyen']);
    $this->createEmployee($this->org->id, ['user_id' => $this->user->id]);

    $this->office = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $this->org->id, 'name' => 'Salena James']);

    $this->service = app(SecureMessageService::class);
});

test('conversations require authentication', function () {
    $this->getJson('/api/conversations')->assertUnauthorized();
});

test('inbox lists the users conversations with last message and unread flag', function () {
    // Office user starts a thread with the caregiver.
    $thread = $this->service->createThread(
        $this->office,
        'Article for you',
        'Hi Designers, Checkout This Article; Learn More About The Laws Of U.I Design.',
        [$this->user->id],
    );

    Sanctum::actingAs($this->user);

    $this->getJson('/api/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject', 'Article for you')
        ->assertJsonPath('data.0.last_message', 'Hi Designers, Checkout This Article; Learn More About The Laws Of U.I Design.')
        ->assertJsonPath('data.0.unread', true)
        ->assertJsonPath('data.0.counterpart.name', 'Salena James');
});

test('opening a conversation returns messages oldest-first and marks it read', function () {
    $thread = $this->service->createThread($this->office, 'Setup help', 'How can we help you?', [$this->user->id]);

    Sanctum::actingAs($this->user);

    $this->getJson("/api/conversations/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.subject', 'Setup help')
        ->assertJsonPath('data.messages.0.body', 'How can we help you?')
        ->assertJsonPath('data.messages.0.is_mine', false);

    // After opening, it should no longer be unread.
    $this->getJson('/api/conversations/unread-count')->assertJsonPath('count', 0);
});

test('a participant can send a message into a conversation', function () {
    $thread = $this->service->createThread($this->office, 'Setup help', 'How can we help you?', [$this->user->id]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/conversations/{$thread->id}/messages", ['body' => 'Account setup help.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Account setup help.')
        ->assertJsonPath('data.is_mine', true);

    $this->getJson("/api/conversations/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.messages.1.body', 'Account setup help.');
});

test('sending a message requires a body', function () {
    $thread = $this->service->createThread($this->office, 'Setup help', 'How can we help you?', [$this->user->id]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/conversations/{$thread->id}/messages", ['body' => ''])
        ->assertStatus(422);
});

test('a non-participant cannot read or post to a conversation', function () {
    $thread = $this->service->createThread($this->office, 'Private', 'Secret', [$this->office->id]);

    $stranger = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    $this->createEmployee($this->org->id, ['user_id' => $stranger->id]);
    Sanctum::actingAs($stranger);

    $this->getJson("/api/conversations/{$thread->id}")->assertForbidden();
    $this->postJson("/api/conversations/{$thread->id}/messages", ['body' => 'hi'])->assertForbidden();
});

test('a caregiver can start a new conversation', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/conversations', [
        'subject'         => 'Question about my shift',
        'body'            => 'Can you confirm Thursday 6:00 PM?',
        'participant_ids' => [$this->office->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Question about my shift');

    Sanctum::actingAs($this->office);
    $this->getJson('/api/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.subject', 'Question about my shift')
        ->assertJsonPath('data.0.unread', true);
});
