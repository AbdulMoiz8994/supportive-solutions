<?php

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access staff module', function () {
    $this->get(route('staff.index'))->assertRedirect(route('signin'));
});

test('employee without staff permission cannot view staff index', function () {
    $employee = $this->createUser(User::ROLE_EMPLOYEE);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('staff.index'))
        ->assertForbidden();
});

test('admin can view staff registry', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createUser(User::ROLE_STAFF, [
        'organization_id' => $org->id,
        'name' => 'Visible Staff',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Staff & AI Agents')
        ->assertSee('Intake Agent');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index', ['tab' => 'staff']))
        ->assertOk()
        ->assertSee('Visible Staff')
        ->assertSee('Add Staff');
});

test('staff store creates invited user with locations', function () {
    Mail::fake();
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $location = Location::first() ?? Location::create(['name' => 'Test Loc', 'state' => 'MI', 'is_active' => true]);
    $role = Role::where('name', User::ROLE_STAFF)->first();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.store'), [
            'name' => 'New Staff Member',
            'email' => 'newstaff@example.com',
            'phone' => '(313) 555-0111',
            'role' => User::ROLE_STAFF,
            'location_ids' => [$location->id],
        ])
        ->assertRedirect(route('staff.index', ['tab' => 'staff']));

    $this->assertDatabaseHas('users', [
        'email' => 'newstaff@example.com',
        'organization_id' => $org->id,
        'role' => User::ROLE_STAFF,
        'is_active' => false,
    ]);

    $user = User::where('email', 'newstaff@example.com')->first();
    expect($user->locations)->toHaveCount(1);
    Mail::assertSent(\App\Mail\WelcomeStaff::class);
});

test('super admin staff store assigns primary organization and appears on staff tab', function () {
    Mail::fake();
    $org = $this->createOrganization();
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);
    $location = Location::first() ?? Location::create(['name' => 'Michigan Main', 'state' => 'MI', 'is_active' => true]);

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('staff.store'), [
            'name' => 'Michigan Staff',
            'email' => 'michigan-staff@example.com',
            'role' => User::ROLE_STAFF,
            'location_ids' => [$location->id],
        ])
        ->assertRedirect(route('staff.index', ['tab' => 'staff']));

    $this->assertDatabaseHas('users', [
        'email' => 'michigan-staff@example.com',
        'organization_id' => $org->id,
    ]);

    $this->actingAsWithTwoFactor($superAdmin)
        ->get(route('staff.index', ['tab' => 'staff']))
        ->assertOk()
        ->assertSee('Michigan Staff');
});

test('staff store validates required fields and unique email', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $location = Location::first();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'role', 'location_ids']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.store'), [
            'name' => 'Dup',
            'email' => $admin->email,
            'role' => User::ROLE_STAFF,
            'location_ids' => [$location->id],
        ])
        ->assertSessionHasErrors(['email']);
});

test('staff update changes user details', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $staff = $this->createUser(User::ROLE_STAFF, [
        'organization_id' => $org->id,
        'name' => 'Old Staff',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('staff.update', $staff->id), [
            'name' => 'Renamed Staff',
            'email' => $staff->email,
            'phone' => '(313) 555-0222',
            'role' => User::ROLE_STAFF,
            'location_ids' => [Location::first()->id],
        ])
        ->assertRedirect();

    expect($staff->fresh()->name)->toBe('Renamed Staff');
});

test('staff toggle flips active status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $staff = $this->createUser(User::ROLE_STAFF, [
        'organization_id' => $org->id,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.toggle', $staff->id))
        ->assertRedirect();

    expect((bool) $staff->fresh()->is_active)->toBeFalse();
});

test('staff show returns 404 for missing user', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.show', 999999))
        ->assertNotFound();
});

test('admin can view ai agent detail', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.agents.show', 'billing'))
        ->assertOk()
        ->assertSee('<title>Billing Agent |', false)
        ->assertSee('Billing Agent')
        ->assertSee('CP-01')
        ->assertSee('Autonomy per action');
});

test('admin can create custom ai agent with platform user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.agents.store'), [
            'name' => 'Custom Test Agent',
            'slug' => 'custom-test',
            'autonomy_mode' => 'approval_required',
            'scope_programs' => ['MICH', 'DHS'],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('ai_agents', [
        'organization_id' => $org->id,
        'slug' => 'custom-test',
        'name' => 'Custom Test Agent',
        'is_custom' => true,
    ]);

    $agent = \App\Models\AiAgent::where('slug', 'custom-test')->first();
    expect($agent->user)->not->toBeNull()
        ->and($agent->user->role)->toBe(User::ROLE_AI_AGENT);
});

test('blank template slug creates custom agent not catalog clone', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.agents.store'), [
            'name' => 'Blank Template Agent',
            'template_slug' => '',
            'autonomy_mode' => 'autonomous',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('ai_agents', [
        'organization_id' => $org->id,
        'name' => 'Blank Template Agent',
        'is_custom' => true,
        'autonomy_mode' => 'autonomous',
    ]);
});

test('admin can update agent scope locations', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $location = Location::first() ?? Location::create(['name' => 'Agent Loc', 'state' => 'MI', 'is_active' => true]);

    $this->actingAsWithTwoFactor($admin)->get(route('staff.agents.show', 'billing'));

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.agents.update', 'billing'), [
            'autonomy_mode' => 'approval_required',
            'scope_location_ids' => [$location->id],
        ])
        ->assertRedirect();

    expect(\App\Models\AiAgent::where('slug', 'billing')->first()->scope_location_ids)
        ->toBe([$location->id]);
});

test('admin can export ai agent fleet as json', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)->get(route('staff.index'));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.agents.export'))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertDownload();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.agents.export.single', 'billing'))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertDownload();
});

test('super admin can view default agents after visiting staff index', function () {
    $this->seed(\Database\Seeders\UserSeeder::class);
    $superAdmin = User::where('role', User::ROLE_SUPER_ADMIN)->first();

    $this->actingAsWithTwoFactor($superAdmin)
        ->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Intake Agent')
        ->assertSee('Billing Agent')
        ->assertDontSee('Shared platform catalog')
        ->assertDontSee('Add to fleet');

    $this->actingAsWithTwoFactor($superAdmin)
        ->get(route('staff.agents.show', 'billing'))
        ->assertOk()
        ->assertSee('Billing Agent');
});

test('admin can pause and disable ai agent', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)->get(route('staff.agents.show', 'billing'));

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.agents.pause', 'billing'))
        ->assertRedirect();

    expect(\App\Models\AiAgent::where('slug', 'billing')->first()->is_paused)->toBeTrue();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.agents.enable', 'billing'))
        ->assertRedirect();

    expect(\App\Models\AiAgent::where('slug', 'billing')->first()->is_enabled)->toBeFalse();
});

test('staff tab excludes ai agent users', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)->get(route('staff.index'));

    $agentUser = \App\Models\AiAgent::where('slug', 'billing')->first()?->user;
    expect($agentUser)->not->toBeNull();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index', ['tab' => 'staff']))
        ->assertOk()
        ->assertDontSee($agentUser->email);
});

test('admin can import ai agent from json export', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)->get(route('staff.index'));

    $payload = [
        'export_version' => 1,
        'name' => 'Imported QA Agent',
        'slug' => 'imported-qa',
        'autonomy_mode' => 'approval_required',
        'scope_programs' => ['MICH'],
        'permission_slugs' => ['view_dashboard'],
        'credential_keys' => [],
        'is_custom' => true,
    ];

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
        'agent.json',
        json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $this->actingAsWithTwoFactor($admin)
        ->post(route('staff.agents.import'), ['import_file' => $file])
        ->assertRedirect(route('staff.index', ['tab' => 'agents']));

    $this->assertDatabaseHas('ai_agents', [
        'organization_id' => $org->id,
        'slug' => 'imported-qa',
        'name' => 'Imported QA Agent',
    ]);
});

test('staff tab only lists users in current organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->createUser(User::ROLE_STAFF, [
        'organization_id' => $orgA->id,
        'name' => 'Org A Staff',
    ]);
    $this->createUser(User::ROLE_STAFF, [
        'organization_id' => $orgB->id,
        'name' => 'Org B Staff',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index', ['tab' => 'staff']))
        ->assertOk()
        ->assertSee('Org A Staff')
        ->assertDontSee('Org B Staff');
});

test('admin can view ai operations tab', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index', ['tab' => 'operations']))
        ->assertOk()
        ->assertSee('Agent leaderboard')
        ->assertSee('Fleet miss-rate');
});
