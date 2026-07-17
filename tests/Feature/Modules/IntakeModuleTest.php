<?php

use App\Models\Client;
use App\Models\Intake;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => seedModuleBasics());

// ── Access control ───────────────────────────────────────────────────────────

test('guest cannot access intake module', function () {
    $this->get(route('intakes.index'))->assertRedirect(route('signin'));
    $this->post(route('intakes.store'), intakePayload())->assertRedirect(route('signin'));
});

test('intake index page renders for admin', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('intakes.index'))
        ->assertOk()
        ->assertSee('Intake')
        ->assertSee('Add New Intake')
        ->assertDontSee('Quick Add');
});

// ── Create (form submission) ─────────────────────────────────────────────────

test('intake store creates a persisted lead record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), intakePayload())
        ->assertRedirect(route('intakes.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('intakes', [
        'first_name' => 'Lead',
        'last_name' => 'Prospect',
        'organization_id' => $org->id,
    ]);
});

test('intake store validates required name fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('intake store rejects malformed email', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), intakePayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors(['email']);
});

test('intake store returns json validation errors for api clients', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('intakes.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name']);
});

// ── Read / show ───────────────────────────────────────────────────────────────

test('intake show displays lead details', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id, ['first_name' => 'Visible', 'last_name' => 'Lead']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('intakes.show', $intake->id))
        ->assertOk()
        ->assertSee('Visible');
});

test('intake show returns 404 for missing record', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('intakes.show', 999999))
        ->assertNotFound();
});

test('intake print view renders for authorized user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('intakes.print', $intake->id))
        ->assertOk();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('intake update persists field changes', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('intakes.update', $intake->id), [
            'first_name' => 'Updated',
            'last_name' => 'Lead',
            'phone' => '(313) 555-0200',
            'email' => 'updated@example.com',
            'source' => 'Hospital',
        ])
        ->assertRedirect(route('intakes.index'))
        ->assertSessionHas('success');

    $intake->refresh();
    expect($intake->first_name)->toBe('Updated')
        ->and($intake->phone)->toBe('(313) 555-0200');
});

// ── Delete ────────────────────────────────────────────────────────────────────

test('intake destroy removes record from database', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('intakes.destroy', $intake->id))
        ->assertRedirect(route('intakes.index'));

    expect(Intake::withoutGlobalScopes()->find($intake->id))->toBeNull();
});

test('admin cannot delete intake from another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $intake = createTestIntake($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->delete(route('intakes.destroy', $intake->id))
        ->assertForbidden();

    expect(Intake::withoutGlobalScopes()->find($intake->id))->not->toBeNull();
});

// ── Relationships & convert ───────────────────────────────────────────────────

test('intake convert creates client and links converted_client_id', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id, ['first_name' => 'Convert', 'last_name' => 'Me']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect();

    $intake->refresh();
    expect($intake->converted_client_id)->not->toBeNull()
        ->and($intake->status)->toBe('Converted');

    $client = Client::withoutGlobalScopes()->find($intake->converted_client_id);
    expect($client)->not->toBeNull()
        ->and($client->first_name)->toBe('Convert')
        ->and($intake->convertedClient->id)->toBe($client->id);
});

test('intake convert cannot run twice on same lead', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)->post(route('intakes.convert', $intake->id));
    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Client::withoutGlobalScopes()->where('first_name', 'Pipeline')->count())->toBe(1);
});

// ── Workflow actions ──────────────────────────────────────────────────────────

test('intake log call appends timestamped note', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id, ['notes' => null]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.log-call', $intake->id), ['note' => 'Left voicemail'])
        ->assertRedirect();

    expect($intake->fresh()->notes)->toContain('Left voicemail');
});

test('intake schedule assessment updates status and notes', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.schedule-assessment', $intake->id), [
            'assessment_date' => '2026-07-01',
        ])
        ->assertRedirect();

    $intake->refresh();
    expect($intake->status)->toBe('Contacted')
        ->and($intake->notes)->toContain('2026-07-01');
});

test('intake mark ineligible updates status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.mark-ineligible', $intake->id))
        ->assertRedirect();

    expect($intake->fresh()->status)->toBe('Ineligible');
});

test('intake document upload creates linked document record', function () {
    Storage::fake('public');
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);
    $file = \Illuminate\Http\UploadedFile::fake()->create('id-scan.pdf', 50, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.upload-document', $intake->id), [
            'file' => $file,
            'name' => 'Lead ID',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('documents', [
        'documentable_type' => 'App\Models\Intake',
        'documentable_id' => $intake->id,
        'name' => 'Lead ID',
    ]);
});
