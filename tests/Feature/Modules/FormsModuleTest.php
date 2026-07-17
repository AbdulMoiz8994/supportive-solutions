<?php

use App\Models\Client;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('admin can view forms page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Test Consent',
        'slug' => 'test-consent',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms'))
        ->assertOk()
        ->assertSee('Forms')
        ->assertSee('Test Consent');
});

test('form fill pre-fills client details', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Anaya', 'last_name' => 'Test']);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent',
        'slug' => 'consent',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name', 'readonly' => true]],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms.fill', ['template' => $template->id, 'subject_id' => $client->id]))
        ->assertOk()
        ->assertSee('Anaya Test');
});

test('signed form is locked', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent',
        'slug' => 'consent-sign',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_SIGNED,
        'locked_at' => now(),
        'signed_at' => now(),
        'signed_by_name' => 'Anaya Test',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('forms.sign', $submission->id), ['signed_by_name' => 'Someone'])
        ->assertSessionHas('error');
});

test('editing template does not change signed submission field values', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Original Name',
        'slug' => 'template-immutable-test',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [
            ['key' => 'full_name', 'label' => 'Name'],
            ['key' => 'consent', 'label' => 'Consent'],
        ],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $snapshot = $template->fields;

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_SIGNED,
        'field_values' => ['full_name' => 'Locked Name', 'consent' => 'Yes'],
        'fields_snapshot' => $snapshot,
        'locked_at' => now(),
    ]);

    $template->update([
        'name' => 'Updated Template Name',
        'fields' => [['key' => 'full_name', 'label' => 'Legal Name']],
    ]);

    $fresh = $submission->fresh();
    expect($fresh->field_values['full_name'])->toBe('Locked Name');
    expect($fresh->fields_snapshot)->toBe($snapshot);
    expect(collect($fresh->fields_snapshot)->pluck('key')->all())->toContain('consent');

    $page = app(\App\Services\FormsTrackingService::class)->showPage($org->id, $submission->id);
    expect(collect($page['fields'])->pluck('key')->all())->toContain('consent');
});

test('admin can view a completed form submission', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'View', 'last_name' => 'Test']);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Viewable Form',
        'slug' => 'viewable-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => false,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_DRAFT,
        'field_values' => ['full_name' => 'View Test'],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms.submissions.show', $submission->id))
        ->assertOk()
        ->assertSee('Viewable Form')
        ->assertSee('View Test');
});

test('admin can edit and delete a draft submission', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Editable Form',
        'slug' => 'editable-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => false,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_DRAFT,
        'field_values' => ['full_name' => 'Before Edit'],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms.submissions.edit', $submission->id))
        ->assertOk()
        ->assertSee('Before Edit');

    $this->actingAsWithTwoFactor($admin)
        ->put(route('forms.submissions.update', $submission->id), [
            'action' => 'save',
            'fields' => ['full_name' => 'After Edit'],
        ])
        ->assertRedirect(route('forms'))
        ->assertSessionHas('success');

    expect($submission->fresh()->field_values['full_name'])->toBe('After Edit');

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('forms.submissions.destroy', $submission->id))
        ->assertRedirect(route('forms'))
        ->assertSessionHas('success');

    expect(FormSubmission::find($submission->id))->toBeNull();
});

test('signed submission cannot be edited or deleted', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Locked Form',
        'slug' => 'locked-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_SIGNED,
        'locked_at' => now(),
        'signed_at' => now(),
        'field_values' => ['full_name' => 'Locked'],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms.submissions.edit', $submission->id))
        ->assertNotFound();

    $this->actingAsWithTwoFactor($admin)
        ->put(route('forms.submissions.update', $submission->id), [
            'action' => 'save',
            'fields' => ['full_name' => 'Quietly changed'],
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($submission->fresh()->field_values['full_name'])->toBe('Locked');

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('forms.submissions.destroy', $submission->id))
        ->assertRedirect(route('forms'))
        ->assertSessionHas('error');
});

test('forms index lists view edit delete actions for drafts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Listed Form',
        'slug' => 'listed-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'is_active' => true,
    ]);

    FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_DRAFT,
        'field_values' => [],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms'))
        ->assertOk()
        ->assertSee('Listed Form')
        ->assertSee('View')
        ->assertSee('Edit')
        ->assertSee('Delete');
});

test('agent generates prefilled draft form for a client', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Anaya', 'last_name' => 'Draft']);
    $agent = \App\Models\AiAgent::withoutGlobalScopes()->firstOrCreate(
        ['organization_id' => $org->id, 'slug' => 'forms'],
        [
            'name' => 'Forms / Documentation Agent',
            'is_enabled' => true,
            'autonomy_mode' => 'approval_required',
        ]
    );

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent Draft',
        'slug' => 'consent-draft-agent',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [
            ['key' => 'full_name', 'label' => 'Name', 'readonly' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ],
        'requires_signature' => true,
        'is_active' => true,
        'is_compliance_required' => true,
    ]);

    $submission = app(\App\Services\FormsTrackingService::class)->generateDraftByAgent(
        $org->id,
        $template->id,
        $client->id,
        $agent,
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_DRAFT);
    expect($submission->created_by_agent_id)->toBe($agent->id);
    expect(data_get($submission->field_values, 'full_name'))->toContain('Anaya');
});

test('generate missing compliance drafts leaves forms as draft not awaiting signature', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Batch', 'last_name' => 'Draft', 'status' => 'Active']);
    $agent = \App\Models\AiAgent::withoutGlobalScopes()->firstOrCreate(
        ['organization_id' => $org->id, 'slug' => 'forms'],
        [
            'name' => 'Forms / Documentation Agent',
            'is_enabled' => true,
            'autonomy_mode' => 'approval_required',
        ]
    );

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Monthly Compliance',
        'slug' => 'monthly-compliance-batch',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [
            ['key' => 'full_name', 'label' => 'Name', 'readonly' => true],
        ],
        'requires_signature' => true,
        'is_active' => true,
        'is_compliance_required' => true,
    ]);

    $result = app(\App\Services\FormsTrackingService::class)
        ->generateMissingComplianceDrafts($org->id);

    expect($result['created'])->toBeGreaterThanOrEqual(1);
    expect($result['agent'])->not->toBeNull();

    $submission = FormSubmission::query()
        ->where('form_template_id', $template->id)
        ->where('subject_id', $client->id)
        ->latest('id')
        ->first();

    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe(FormSubmission::STATUS_DRAFT);
    expect($submission->created_by_agent_id)->toBe($agent->id);
    expect($submission->status)->not->toBe(FormSubmission::STATUS_AWAITING_SIGNATURE);
});

test('disabled forms agent does not generate compliance drafts', function () {
    $org = $this->createOrganization();
    $this->createClient($org->id, ['first_name' => 'No', 'last_name' => 'Draft', 'status' => 'Active']);
    $agent = \App\Models\AiAgent::withoutGlobalScopes()->firstOrCreate(
        ['organization_id' => $org->id, 'slug' => 'forms'],
        [
            'name' => 'Forms / Documentation Agent',
            'is_enabled' => true,
            'autonomy_mode' => 'approval_required',
        ]
    );
    $agent->update(['is_enabled' => false, 'is_paused' => true]);

    FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Disabled Agent Form',
        'slug' => 'disabled-agent-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name', 'readonly' => true]],
        'requires_signature' => true,
        'is_active' => true,
        'is_compliance_required' => true,
    ]);

    $result = app(\App\Services\FormsTrackingService::class)
        ->generateMissingComplianceDrafts($org->id);

    expect($result['created'])->toBe(0);
    expect($result['agent'])->toBeNull();
    expect(FormSubmission::query()->where('organization_id', $org->id)->count())->toBe(0);
});

test('forms generate-drafts artisan command respects disabled agent', function () {
    $org = $this->createOrganization();
    $this->createClient($org->id, ['status' => 'Active']);
    $agent = \App\Models\AiAgent::withoutGlobalScopes()->firstOrCreate(
        ['organization_id' => $org->id, 'slug' => 'forms'],
        ['name' => 'Forms Agent', 'is_enabled' => true, 'autonomy_mode' => 'approval_required']
    );
    $agent->update(['is_enabled' => false]);

    FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Cmd Form',
        'slug' => 'cmd-form-drafts',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'requires_signature' => true,
        'is_active' => true,
        'is_compliance_required' => true,
    ]);

    $this->artisan('forms:generate-drafts', ['--org' => $org->id])
        ->expectsOutputToContain('Forms agent unavailable')
        ->assertSuccessful();
});

test('docusign envelope path sets esign channel when client succeeds', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['email' => 'docusign@example.com', 'first_name' => 'Doc', 'last_name' => 'Sign']);

    \Illuminate\Support\Facades\Mail::fake();

    $this->mock(\App\Services\Integrations\DocuSignClient::class, function ($mock) {
        $mock->shouldReceive('createEnvelopeForForm')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Envelope created',
                'envelope_id' => 'env-123',
            ]);
    });

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'DocuSign Form',
        'slug' => 'docusign-form-path',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = app(\App\Services\FormsTrackingService::class)->storeSubmission(
        $org->id,
        $template->id,
        $client->id,
        ['full_name' => 'Doc Sign'],
        $admin,
        'send_signature',
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_AWAITING_SIGNATURE);
    expect($submission->esign_channel)->toBe('docusign');
    expect($submission->esign_external_id)->toBe('env-123');
});

test('sending for signature freezes fields snapshot', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Snapshot Form',
        'slug' => 'snapshot-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [
            ['key' => 'full_name', 'label' => 'Name'],
            ['key' => 'consent', 'label' => 'Consent'],
        ],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('forms.store', $template->id), [
            'subject_id' => $client->id,
            'action' => 'send_signature',
            'fields' => [
                'full_name' => 'Anaya Test',
                'consent' => 'Yes',
            ],
        ])
        ->assertRedirect();

    $submission = FormSubmission::where('form_template_id', $template->id)->latest('id')->first();
    expect($submission->status)->toBe(FormSubmission::STATUS_AWAITING_SIGNATURE);
    expect($submission->fields_snapshot)->not->toBeNull();
    expect(collect($submission->fields_snapshot)->pluck('key')->all())->toContain('consent');

    $template->update([
        'fields' => [['key' => 'full_name', 'label' => 'Name only']],
    ]);

    $page = app(\App\Services\FormsTrackingService::class)->editPage($org->id, $submission->id);
    expect(collect($page['fields'])->pluck('key')->all())->toContain('consent');
});

// ── Client acceptance checklist (How to test it) ─────────────────────────────

test('sign now files pdf into client documents folder as forms', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Signed', 'last_name' => 'Client']);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Intake Form',
        'slug' => 'intake-sign-docs',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => true,
        'is_compliance_required' => false,
        'is_active' => true,
    ]);

    $submission = app(\App\Services\FormsTrackingService::class)->storeSubmission(
        $org->id,
        $template->id,
        $client->id,
        ['full_name' => 'Signed Client', 'signature_name' => 'Signed Client'],
        $admin,
        'sign',
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_SIGNED);
    expect($submission->document_id)->not->toBeNull();

    $document = \App\Models\Document::find($submission->document_id);
    expect($document)->not->toBeNull();
    expect($document->documentable_type)->toBe(Client::class);
    expect($document->documentable_id)->toBe($client->id);
    expect($document->type)->toBe('form');
    expect($document->category)->toBe('Forms');
    expect($document->is_signed)->toBeTrue();

    $folder = app(\App\Services\ClientDocumentsExportService::class)->folderFor($document);
    expect($folder)->toBe('forms');

    \Illuminate\Support\Facades\Storage::disk('local')->assertExists($document->path);
    expect(\Illuminate\Support\Facades\Storage::disk('local')->get($document->path))->toStartWith('%PDF');
});

test('send for e-signature sets awaiting signature until signed', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'email' => 'await@example.com',
        'first_name' => 'Await',
        'last_name' => 'Signer',
    ]);

    \Illuminate\Support\Facades\Mail::fake();

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent Awaiting',
        'slug' => 'consent-awaiting-module',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = app(\App\Services\FormsTrackingService::class)->storeSubmission(
        $org->id,
        $template->id,
        $client->id,
        ['full_name' => 'Await Signer'],
        $admin,
        'send_signature',
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_AWAITING_SIGNATURE);
    expect($submission->signing_token)->not->toBeEmpty();
    expect($submission->esign_sent_at)->not->toBeNull();
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\FormEsignRequestMail::class);

    $index = $this->actingAsWithTwoFactor($admin)
        ->get(route('forms', ['status' => FormSubmission::STATUS_AWAITING_SIGNATURE]))
        ->assertOk();

    $index->assertSee('Consent Awaiting');
    $index->assertSee('Awaiting signature');
});

test('signed required form appears on compliance page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent to Care',
        'slug' => 'consent-compliance-module',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'is_compliance_required' => true,
        'requires_signature' => true,
        'is_active' => true,
        'fields' => [],
    ]);

    FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_SIGNED,
        'signed_at' => now(),
        'signed_by_name' => 'Test Client',
        'locked_at' => now(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('Signed Compliance Forms')
        ->assertSee('Consent to Care');
});

test('forms agent generate and send produces awaiting signature prefilled form', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, [
        'first_name' => 'Robot',
        'last_name' => 'Draft',
        'email' => 'robot@example.com',
    ]);

    \Illuminate\Support\Facades\Mail::fake();

    $agent = \App\Models\AiAgent::withoutGlobalScopes()->firstOrCreate(
        ['organization_id' => $org->id, 'slug' => 'forms'],
        [
            'name' => 'Forms / Documentation Agent',
            'is_enabled' => true,
            'is_paused' => false,
            'autonomy_mode' => 'approval_required',
        ]
    );
    $agent->update(['is_enabled' => true, 'is_paused' => false]);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Agent Send Form',
        'slug' => 'agent-send-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [
            ['key' => 'full_name', 'label' => 'Name', 'readonly' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = app(\App\Services\FormsTrackingService::class)->generateAndSendByAgent(
        $org->id,
        $template->id,
        $client->id,
        $agent,
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_AWAITING_SIGNATURE);
    expect($submission->created_by_agent_id)->toBe($agent->id);
    expect(data_get($submission->field_values, 'full_name'))->toContain('Robot');
    expect($submission->signing_token)->not->toBeEmpty();
    expect($submission->esign_channel)->not->toBeEmpty();
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\FormEsignRequestMail::class);
});

test('forms index shows use fill out and signed complete labels', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Label Check Form',
        'slug' => 'label-check-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_SIGNED,
        'signed_at' => now(),
        'locked_at' => now(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms'))
        ->assertOk()
        ->assertSee('Use / Fill out')
        ->assertSee('Filled forms')
        ->assertSee('Signed / Complete');
});

test('forms listing filters by status template person type search and date', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Filter', 'last_name' => 'Client']);
    $caregiver = $this->createEmployee($org->id, ['first_name' => 'Filter', 'last_name' => 'Caregiver']);

    $clientTemplate = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Client Consent Filter',
        'slug' => 'client-consent-filter',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'is_active' => true,
    ]);

    $caregiverTemplate = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Caregiver Agreement Filter',
        'slug' => 'caregiver-agreement-filter',
        'target_type' => FormTemplate::TARGET_EMPLOYEE,
        'fields' => [],
        'is_active' => true,
    ]);

    $match = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $clientTemplate->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_DRAFT,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $caregiverTemplate->id,
        'subject_type' => \App\Models\Employee::class,
        'subject_id' => $caregiver->id,
        'status' => FormSubmission::STATUS_SIGNED,
        'signed_at' => now(),
        'locked_at' => now(),
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms', [
            'status' => FormSubmission::STATUS_DRAFT,
            'template_id' => $clientTemplate->id,
            'target_type' => FormTemplate::TARGET_CLIENT,
            'search' => 'Filter Client',
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Client Consent Filter')
        ->assertSee('Filter Client')
        ->assertDontSee('Filter Caregiver')
        ->assertSee('Apply filters');

    $filtered = app(\App\Services\FormsTrackingService::class)->pageData(
        $org->id,
        \Illuminate\Http\Request::create('/forms', 'GET', [
            'status' => FormSubmission::STATUS_DRAFT,
            'template_id' => $clientTemplate->id,
            'target_type' => FormTemplate::TARGET_CLIENT,
            'search' => 'Filter Client',
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]),
        $admin,
    );

    expect($filtered['submissions']->pluck('id')->all())->toBe([$match->id]);
});

test('forms listing paginates filled forms and preserves filters', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Page', 'last_name' => 'Client']);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Paged Form',
        'slug' => 'paged-form',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'is_active' => true,
    ]);

    foreach (range(1, 12) as $i) {
        FormSubmission::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'form_template_id' => $template->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'status' => FormSubmission::STATUS_DRAFT,
            'field_values' => ['n' => $i],
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ]);
    }

    $page1 = $this->actingAsWithTwoFactor($admin)
        ->get(route('forms', [
            'status' => FormSubmission::STATUS_DRAFT,
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertSee('Showing 1–10')
        ->assertSee('of 12')
        ->assertSee('Page 1 of 2');

    $page1->assertSee('page=2', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms', [
            'status' => FormSubmission::STATUS_DRAFT,
            'per_page' => 10,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertSee('Showing 11–12')
        ->assertSee('status=draft', false);
});
