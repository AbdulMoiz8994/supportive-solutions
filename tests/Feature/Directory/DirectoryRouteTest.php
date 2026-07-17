<?php

use App\Helpers\MenuHelper;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('contacts route redirects to directory', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get('/contacts')
        ->assertRedirect(route('directory'));
});

test('menu directory path resolves to directory route', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $this->actingAs($user);

    $items = collect(MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->firstWhere('name', 'Directory');

    expect($items)->not->toBeNull()
        ->and($items['path'])->toBe('/directory');
});

test('contact creation stores organization_id', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.store'), [
            'name' => 'Dr. Smith',
            'type' => Contact::TYPE_PCP,
            'phone' => '(555) 123-4567',
            'clinic_name' => 'City Clinic',
            'is_active' => '1',
        ])
        ->assertRedirect(route('directory'))
        ->assertSessionHas('success');

    $contact = Contact::withoutGlobalScopes()->latest('id')->first();

    expect($contact->organization_id)->toBe($org->id)
        ->and($contact->name)->toBe('Dr. Smith');
});

test('contacts from other organizations are not visible', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);

    Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $orgA->id,
        'name' => 'Org A Physician',
        'type' => Contact::TYPE_PCP,
        'is_active' => true,
    ]);

    Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $orgB->id,
        'name' => 'Org B Physician',
        'type' => Contact::TYPE_PCP,
        'is_active' => true,
    ]);

    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->get(route('directory'))
        ->assertOk()
        ->assertSee('Org A Physician')
        ->assertDontSee('Org B Physician');
});
