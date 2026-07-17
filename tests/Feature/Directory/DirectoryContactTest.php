<?php

use App\Helpers\MenuHelper;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validContactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Smith',
        'type' => Contact::TYPE_PCP,
        'phone' => '(555) 123-4567',
        'fax' => '(555) 123-4568',
        'email' => 'jane@example.com',
        'clinic_name' => 'Example Clinic',
        'job_title' => 'Physician',
        'is_active' => '1',
    ], $overrides);
}

test('guest cannot access directory pages', function () {
    $this->get(route('directory'))->assertRedirect(route('signin'));
    $this->get(route('directory.create'))->assertRedirect(route('signin'));
    $this->post(route('directory.store'), validContactPayload())->assertRedirect(route('signin'));
});

test('unauthorized authenticated user cannot access directory module', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('directory'))
        ->assertForbidden();
});

test('contacts route redirects to directory', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get('/contacts')
        ->assertRedirect(route('directory'));
});

test('menu shows directory label and path', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $this->actingAs($user);

    $items = collect(MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->firstWhere('name', 'Directory');

    expect($items)->not->toBeNull()
        ->and($items['path'])->toBe('/directory');
});

test('authorized user can view directory index', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createContact($org->id, ['name' => 'Visible Contact']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory'))
        ->assertOk()
        ->assertSee('Visible Contact')
        ->assertSee('Directories');
});

test('authorized user can create a directory contact with valid data', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.store'), validContactPayload())
        ->assertRedirect(route('directory'))
        ->assertSessionHas('success');

    $contact = Contact::withoutGlobalScopes()->latest('id')->first();

    expect($contact->organization_id)->toBe($org->id)
        ->and($contact->name)->toBe('Jane Smith')
        ->and($contact->type)->toBe(Contact::TYPE_PCP)
        ->and($contact->is_active)->toBeTrue();
});

test('super admin can create directory contact without user organization', function () {
    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);

    $this->actingAsWithTwoFactor($super)
        ->post(route('directory.store'), validContactPayload([
            'name' => 'Taimoor Hussain',
            'email' => 'taimoor.kingdomvision@gmail.com',
            'type' => Contact::TYPE_PCP,
            'job_title' => 'Case Manager',
            'phone' => '03131094717',
            'clinic_name' => 'Kingdom vision',
        ]))
        ->assertRedirect(route('directory'))
        ->assertSessionHas('success');

    $contact = Contact::withoutGlobalScopes()->where('email', 'taimoor.kingdomvision@gmail.com')->first();

    expect($contact)->not->toBeNull()
        ->and($contact->organization_id)->toBe($org->id);
});

test('validation rejects invalid directory contact data', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.store'), [
            'name' => '',
            'type' => 'Invalid Type',
            'email' => 'not-an-email',
            'notes' => str_repeat('a', 5001),
        ])
        ->assertSessionHasErrors(['name', 'type', 'email', 'notes']);
});

test('authorized user can update a directory contact', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, ['name' => 'Original Name']);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('directory.update', $contact->id), validContactPayload([
            'name' => 'Updated Name',
            'type' => Contact::TYPE_VENDOR,
            'is_active' => '0',
        ]))
        ->assertRedirect(route('directory.show', $contact->id));

    $contact->refresh();

    expect($contact->name)->toBe('Updated Name')
        ->and($contact->type)->toBe(Contact::TYPE_VENDOR)
        ->and($contact->is_active)->toBeFalse();
});

test('authorized user can delete a directory contact', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('directory.destroy', $contact->id))
        ->assertRedirect(route('directory'));

    expect(Contact::withoutGlobalScopes()->find($contact->id))->toBeNull();
});

test('search finds contacts by name phone email organization and type', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createContact($org->id, [
        'name' => 'Alpha Physician',
        'phone' => '(555) 111-2222',
        'email' => 'alpha@clinic.test',
        'clinic_name' => 'Alpha Medical Group',
        'type' => Contact::TYPE_PCP,
    ]);

    $this->createContact($org->id, [
        'name' => 'Beta Vendor',
        'type' => Contact::TYPE_VENDOR,
    ]);

    $this->actingAsWithTwoFactor($admin);

    $this->get(route('directory', ['search' => 'Alpha Physician']))->assertOk()->assertSee('Alpha Physician')->assertDontSee('Beta Vendor');
    $this->get(route('directory', ['search' => 'alpha@clinic']))->assertOk()->assertSee('Alpha Physician');
    $this->get(route('directory', ['search' => '5551112222']))->assertOk()->assertSee('Alpha Physician');
    $this->get(route('directory', ['search' => 'Alpha Medical']))->assertOk()->assertSee('Alpha Physician');
    $this->get(route('directory', ['search' => Contact::TYPE_PCP]))->assertOk()->assertSee('Alpha Physician');
});

test('filters correctly filter by type and status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createContact($org->id, ['name' => 'Active PCP', 'type' => Contact::TYPE_PCP, 'is_active' => true]);
    $this->createContact($org->id, ['name' => 'Inactive Vendor', 'type' => Contact::TYPE_VENDOR, 'is_active' => false]);

    $this->actingAsWithTwoFactor($admin);

    $this->get(route('directory', ['type' => Contact::TYPE_PCP]))
        ->assertOk()
        ->assertSee('Active PCP')
        ->assertDontSee('Inactive Vendor');

    $this->get(route('directory', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Inactive Vendor')
        ->assertDontSee('Active PCP');
});

test('contacts from other organizations are not visible', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);

    $this->createContact($orgA->id, ['name' => 'Org A Contact']);
    $this->createContact($orgB->id, ['name' => 'Org B Contact']);

    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->get(route('directory'))
        ->assertOk()
        ->assertSee('Org A Contact')
        ->assertDontSee('Org B Contact');
});

test('user cannot view update or delete contact from another organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $contact = $this->createContact($orgA->id, ['name' => 'Protected Contact']);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('directory.show', $contact->id))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($adminB)
        ->put(route('directory.update', $contact->id), validContactPayload(['name' => 'Hacked']))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($adminB)
        ->delete(route('directory.destroy', $contact->id))
        ->assertForbidden();
});

test('location filter prevents leaking contacts from another location', function () {
    $org = $this->createOrganization();
    $locationA = Location::create(['name' => 'Location A', 'state' => 'MI']);
    $locationB = Location::create(['name' => 'Location B', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createContact($org->id, ['name' => 'Location A Contact', 'location_id' => $locationA->id]);
    $this->createContact($org->id, ['name' => 'Location B Contact', 'location_id' => $locationB->id]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $locationA->id])
        ->get(route('directory'))
        ->assertOk()
        ->assertSee('Location A Contact')
        ->assertDontSee('Location B Contact');
});

test('blade output escapes unsafe contact values', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $xss = '<script>alert(1)</script>';
    $contact = $this->createContact($org->id, [
        'name' => $xss,
        'notes' => $xss,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.show', $contact->id))
        ->assertOk()
        ->assertSee(htmlspecialchars($xss, ENT_QUOTES, 'UTF-8'), false)
        ->assertDontSee($xss, false);
});

test('mass assignment cannot set protected organization fields', function () {
    $org = $this->createOrganization();
    $otherOrg = $this->createOrganization(['name' => 'Other Org']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.store'), validContactPayload([
            'organization_id' => $otherOrg->id,
            'location_id' => 99999,
        ]))
        ->assertRedirect(route('directory'));

    $contact = Contact::withoutGlobalScopes()->latest('id')->first();

    expect($contact->organization_id)->toBe($org->id)
        ->and($contact->location_id)->not->toBe(99999);
});

test('audit log is written on create update and delete', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.store'), validContactPayload(['name' => 'Audit Contact']))
        ->assertRedirect(route('directory'));

    $contact = Contact::withoutGlobalScopes()->where('name', 'Audit Contact')->first();

    expect(ActivityLog::where('subject_type', Contact::class)->where('subject_id', $contact->id)->count())->toBe(1);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('directory.update', $contact->id), validContactPayload(['name' => 'Audit Contact Updated']))
        ->assertRedirect(route('directory.show', $contact->id));

    expect(ActivityLog::where('subject_type', Contact::class)->where('subject_id', $contact->id)->count())->toBe(2);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('directory.destroy', $contact->id))
        ->assertRedirect(route('directory'));

    expect(ActivityLog::where('subject_type', Contact::class)->where('subject_id', $contact->id)->count())->toBe(3);
});

test('create page renders grouped form sections', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.create'))
        ->assertOk()
        ->assertSee('Basic Information')
        ->assertSee('Organization')
        ->assertSee('Contact Information')
        ->assertSee('Address')
        ->assertSee('Internal Notes')
        ->assertSee('Save entry');
});

test('edit page renders contact values and record metadata', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, ['name' => 'Editable Contact', 'job_title' => 'Coordinator']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.edit', $contact->id))
        ->assertOk()
        ->assertSee('Editable Contact')
        ->assertSee('Last updated')
        ->assertSee('Save changes')
        ->assertSee('value="Coordinator"', false);
});

test('vendor show page renders integration layout from figma', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, [
        'name' => 'AccountantsWorld',
        'type' => Contact::TYPE_VENDOR,
        'job_title' => 'Payroll provider',
        'integration_slug' => 'accountantsworld',
        'integration_credential_key' => \App\Models\IntegrationCredential::KEY_ACCOUNTANTSWORLD,
        'data_flow' => 'Verified hours out → batch built (1st Tue) → pay stubs + tax back',
        'app_area' => 'payroll',
        'owning_agent' => 'Payroll agent',
        'clinic_name' => 'Payroll tab',
        'provider_id' => 'AW-MI-0883',
        'phone' => '(888) 999-1366',
        'email' => 'tbeckett@accountantsworld.com',
        'notes' => "Verified hours out → batch built (1st Tue) → pay stubs + tax back",
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.show', $contact->id))
        ->assertOk()
        ->assertSee('Vendors / integrations')
        ->assertSee('Test connection')
        ->assertSee('Integration')
        ->assertSee('Support')
        ->assertSee('Connection Health')
        ->assertSee('Last batch')
        ->assertSee('Credential Vault')
        ->assertSee('Payroll tab')
        ->assertSee('Payroll agent');
});

test('vendor test connection persists health snapshot', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, [
        'name' => 'RingCentral',
        'type' => Contact::TYPE_VENDOR,
        'integration_slug' => 'ringcentral',
        'integration_credential_key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL,
    ]);

    \App\Models\IntegrationCredential::query()->create([
        'key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL,
        'api_key' => 'client-id',
        'password' => 'client-secret',
        'metadata' => ['server_url' => 'https://platform.ringcentral.com'],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    \Illuminate\Support\Facades\Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => \Illuminate\Support\Facades\Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
            'scope' => 'ReadAccounts SMS Fax',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~' => \Illuminate\Support\Facades\Http::response([
            'extensionNumber' => '101',
        ]),
        // Keep the health check hermetic: without this fake the SMS sender
        // lookup makes a live HTTP call and the result depends on network.
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/phone-number' => \Illuminate\Support\Facades\Http::response([
            'records' => [['phoneNumber' => '+15550100', 'features' => ['SmsSender']]],
        ]),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('directory.test-connection', $contact->id))
        ->assertRedirect(route('directory.show', $contact->id))
        ->assertSessionHas('success');

    $health = $contact->fresh()->connectionHealth;
    expect($health)->not->toBeNull()
        ->and($health->status)->toBe(\App\Models\IntegrationConnectionHealth::STATUS_CONNECTED)
        ->and($health->last_tested_at)->not->toBeNull();
});

test('system show page renders state portal layout from figma', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, [
        'name' => 'CHAMPS',
        'type' => Contact::TYPE_OTHER,
        'job_title' => 'Community Health Automated Medicaid Processing System',
        'clinic_name' => 'MDHHS',
        'notes' => "Provider enrollment · caregiver CHAMPS association · billing eligibility\n\nRPA (browser automation) via MILogin — no public API\n\nOne-time association at hiring + continuous monitor\n\nAssociates each caregiver; records the CHAMPS Association Date (drives pay eligibility)\n\nMonitors enrollment/sanction status; flags changes to your queue",
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.show', $contact->id))
        ->assertOk()
        ->assertSee('State systems & portals')
        ->assertSee('State system')
        ->assertSee('Credentials')
        ->assertSee('Purpose & Access')
        ->assertSee('What the AI Agent Does Here')
        ->assertSee('At a glance');
});

test('show page renders profile sections and quick actions', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = $this->createContact($org->id, [
        'name' => 'Profile Contact',
        'type' => Contact::TYPE_PCP,
        'phone' => '(555) 999-0000',
        'email' => 'profile@example.com',
        'clinic_name' => 'Example Org',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory.show', $contact->id))
        ->assertOk()
        ->assertSee('Profile Contact')
        ->assertSee('Contacts')
        ->assertSee('At a glance')
        ->assertSee('Used for')
        ->assertSee('Example Org')
        ->assertSee('tel:5559990000', false)
        ->assertSee('mailto:profile@example.com', false)
        ->assertSee('Delete contact');
});

test('validation errors are displayed on create form', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->from(route('directory.create'))
        ->post(route('directory.store'), ['name' => '', 'type' => 'Invalid'])
        ->assertSessionHasErrors(['name', 'type']);

    $this->actingAsWithTwoFactor($admin)
        ->withViewErrors(['name' => ['The name field is required.']])
        ->get(route('directory.create'))
        ->assertOk()
        ->assertSee('Please correct the following')
        ->assertSee('The name field is required');
});

test('flash success message displays on directory index', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory'))
        ->assertOk()
        ->assertSessionHasNoErrors();

    $response = $this->actingAsWithTwoFactor($admin)
        ->withSession(['success' => 'Contact added to directory.'])
        ->get(route('directory'));

    $response->assertOk()->assertSee('Contact added to directory.');
});

test('directory filters persist in session after filtered index visit', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createContact($org->id, ['name' => 'Filtered Contact', 'type' => Contact::TYPE_VENDOR]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory', ['type' => Contact::TYPE_VENDOR, 'search' => 'Filtered']))
        ->assertOk()
        ->assertSee('Filtered Contact');

    expect(session('directory.filters'))->toMatchArray([
        'type' => Contact::TYPE_VENDOR,
        'search' => 'Filtered',
    ]);
});

test('delete redirects back to directory with preserved session filters', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $keep = $this->createContact($org->id, ['name' => 'Keep Me', 'type' => Contact::TYPE_PCP]);
    $remove = $this->createContact($org->id, ['name' => 'Remove Me', 'type' => Contact::TYPE_PCP]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['directory.filters' => ['type' => Contact::TYPE_PCP]])
        ->delete(route('directory.destroy', $remove->id), [
            'return_filters' => json_encode(['type' => Contact::TYPE_PCP]),
        ])
        ->assertRedirect(route('directory', ['type' => Contact::TYPE_PCP]))
        ->assertSessionHas('success');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('directory', ['type' => Contact::TYPE_PCP]))
        ->assertOk()
        ->assertSee('Keep Me')
        ->assertDontSee('Remove Me');
});
