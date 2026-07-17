<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('directory show returns 404 for missing contact', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.show', 999999))
        ->assertNotFound();
});

test('directory edit returns 404 for missing contact', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.edit', 999999))
        ->assertNotFound();
});

test('directory destroy returns 404 for missing contact', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('directory.destroy', 999999))
        ->assertNotFound();
});

test('directory store returns json validation errors', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('directory.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'type']);
});

test('directory contact client pivot relationship works both ways', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $contact = $this->createContact($org->id, [
        'name' => 'Dr. Linked',
        'type' => Contact::TYPE_PCP,
    ]);

    $contact->clients()->attach($client->id, ['role' => 'Primary Care Physician']);

    expect($contact->clients)->toHaveCount(1)
        ->and($contact->clients->first()->id)->toBe($client->id)
        ->and($client->contacts->first()->id)->toBe($contact->id);
});

test('directory category filter limits results to physician contacts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createContact($org->id, ['name' => 'Physician One', 'type' => Contact::TYPE_PCP]);
    $this->createContact($org->id, ['name' => 'Vendor One', 'type' => Contact::TYPE_VENDOR]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory', ['category' => 'physicians']))
        ->assertOk()
        ->assertSee('Physician One')
        ->assertDontSee('Vendor One');
});

test('directory update rejects invalid email', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('directory.update', $contact->id), [
            'name' => 'Valid Name',
            'type' => Contact::TYPE_PCP,
            'email' => 'not-valid-email',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors(['email']);

    expect($contact->fresh()->email)->not->toBe('not-valid-email');
});

test('directory destroy removes contact and keeps unrelated contacts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $keep = $this->createContact($org->id, ['name' => 'Keep Contact']);
    $remove = $this->createContact($org->id, ['name' => 'Remove Contact']);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('directory.destroy', $remove->id))
        ->assertRedirect(route('directory'));

    expect(Contact::withoutGlobalScopes()->find($remove->id))->toBeNull()
        ->and(Contact::withoutGlobalScopes()->find($keep->id))->not->toBeNull();
});

test('directory show page loads for each major contact type without error', function (string $type, string $label) {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, ['name' => $label, 'type' => $type]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.show', $contact->id))
        ->assertOk()
        ->assertSee($label);
})->with([
    'pcp' => [Contact::TYPE_PCP, 'Dr. Type Test'],
    'vendor' => [Contact::TYPE_VENDOR, 'Vendor Type Test'],
    'case coordinator' => [Contact::TYPE_CASE_COORDINATOR, 'Coordinator Type Test'],
]);

test('directory store with minimal valid payload creates active contact', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.store'), [
            'name' => 'Minimal Contact',
            'type' => Contact::TYPE_REFERRAL,
            'is_active' => '1',
        ])
        ->assertRedirect(route('directory'));

    $this->assertDatabaseHas('contacts', [
        'name' => 'Minimal Contact',
        'organization_id' => $org->id,
        'type' => Contact::TYPE_REFERRAL,
    ]);
});
