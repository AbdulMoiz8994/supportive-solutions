<?php

use App\Models\AiAgent;
use App\Models\ComplianceForm;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => seedModuleBasics());

// ── D9 — signed forms: real PDF + compliance update ─────────────────────────

test('signing a compliance form stores a real pdf and marks the period submitted', function () {
    Storage::fake('local');

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Pdf', 'last_name' => 'Client']);
    $caregiver = $this->createEmployee($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Monthly Compliance Certification',
        'slug' => 'monthly-compliance',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Client name']],
        'requires_signature' => true,
        'is_compliance_required' => true,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => \App\Models\Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_DRAFT,
        'field_values' => ['full_name' => 'Pdf Client'],
    ]);

    $dueForm = ComplianceForm::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'client_id' => $client->id,
        'period' => now()->format('Y-m'),
        'status' => ComplianceForm::STATUS_DUE,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('forms.sign', $submission->id), ['signed_by_name' => 'Pdf Client'])
        ->assertRedirect();

    $submission->refresh();
    $document = $submission->document;

    expect($submission->status)->toBe(FormSubmission::STATUS_SIGNED)
        ->and($document)->not->toBeNull()
        ->and($document->category)->toBe('Compliance')
        ->and($document->file_size)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($document->path);
    expect(Storage::disk('local')->get($document->path))->toStartWith('%PDF');

    // The current-period compliance form flips Due → Submitted on sign.
    expect($dueForm->fresh()->status)->toBe(ComplianceForm::STATUS_SUBMITTED)
        ->and($dueForm->fresh()->submitted_at)->not->toBeNull();
});

test('download serves the stored signed pdf', function () {
    Storage::fake('local');

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent',
        'slug' => 'consent-pdf',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => \App\Models\Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_DRAFT,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('forms.sign', $submission->id), ['signed_by_name' => 'Signer'])
        ->assertRedirect();

    $response = $this->get(route('forms.download', $submission->id));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

// ── A9 — sidebar badge live refresh ──────────────────────────────────────────

test('sidebar badges endpoint returns shared workflow and client counts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['status' => 'Active']);

    $response = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('sidebar.badges'))
        ->assertOk()
        ->assertJsonStructure(['/workflow-queues', '/clients']);

    $expected = app(\App\Services\WorkflowQueueService::class)->approvalCount($org->id);

    expect($response->json('/workflow-queues'))->toBe($expected);
});

test('sidebar badges endpoint is blocked for caregiver employees', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->getJson(route('sidebar.badges'))
        ->assertForbidden();
});

// ── A10 — page titles ─────────────────────────────────────────────────────────

test('workflow queues and staff pages render their own titles', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get('/workflow-queues')
        ->assertOk()
        ->assertSee('<title>Workflow Queues |', false);

    $this->actingAsWithTwoFactor($admin)
        ->get('/staff')
        ->assertOk()
        ->assertSee('<title>Staff &amp; AI Agents |', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.agents.show', 'billing'))
        ->assertOk()
        ->assertSee('<title>Billing Agent |', false);

    $template = \App\Models\FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent Form',
        'slug' => 'consent-form-title-test',
        'target_type' => \App\Models\FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('forms.fill', $template->id))
        ->assertOk()
        ->assertSee('<title>Consent Form |', false);
});

test('tab-query sub-routes render contextual browser titles', function () {
    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Tab', 'last_name' => 'Client']);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'first_name' => 'Tab',
        'last_name' => 'Caregiver',
    ]);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global', ['tab' => 'integrations']))
        ->assertOk()
        ->assertSee('<title>Integrations &amp; Connections — Global Settings |', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'billing']))
        ->assertOk()
        ->assertSee('<title>Billing History — Tab Client |', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.show', ['id' => $caregiver->id, 'tab' => 'checks']))
        ->assertOk()
        ->assertSee('<title>Background Checks — Tab Caregiver |', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index', ['tab' => 'operations']))
        ->assertOk()
        ->assertSee('<title>AI Operations — Staff &amp; AI Agents |', false);
});

// ── D8 — individual integration rows ─────────────────────────────────────────

test('executive report uses monthly forms rate label not compliance rate', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Monthly forms rate', false)
        ->assertDontSee('Compliance rate', false);
});

test('integrations settings lists each state portal as its own row', function () {
    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global', ['tab' => 'integrations']))
        ->assertOk()
        ->assertSee('CHAMPS / MILogin')
        ->assertSee('MDHHS / Bridges')
        ->assertSee('Sigma Portal')
        ->assertSee('ICHAT')
        ->assertSee('DocuSign')
        ->assertDontSee('CHAMPS · MDHHS · Sigma · ICHAT');
});

test('integrations settings shows the retell row with a testable connection', function () {
    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global', ['tab' => 'integrations']))
        ->assertOk()
        ->assertSee('Retell AI');

    // Unconfigured Retell reports "not configured" instead of erroring.
    $payload = app(\App\Services\GlobalIntegrationTestService::class)->test('retell');

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(\App\Models\GlobalIntegrationHealth::STATUS_NOT_CONFIGURED);
});

// ── D10 — ICHAT annual renewal tasks ─────────────────────────────────────────

test('background check batch raises an idempotent ichat renewal task', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Active']);

    $agent = AiAgent::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'slug' => 'background',
        'name' => 'Background Checks Agent',
        'is_enabled' => true,
    ]);

    \App\Models\BackgroundCheck::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'type' => 'ICHAT',
        'label' => 'ICHAT',
        'cadence' => 'Annual',
        'status' => 'Clear',
        'next_due' => today()->addDays(20),
    ]);

    $this->artisan('background-checks:run-batch')->assertSuccessful();
    $this->artisan('background-checks:run-batch')->assertSuccessful(); // idempotent rerun

    $renewalTasks = Task::query()
        ->where('related_type', \App\Models\BackgroundCheck::class)
        ->where('source', Task::SOURCE_SYSTEM)
        ->get();

    expect($renewalTasks)->toHaveCount(1)
        ->and($renewalTasks->first()->title)->toContain('Re-run ICHAT')
        ->and($renewalTasks->first()->assignee_agent_id)->toBe($agent->id);
});

// ── A9 — badge cache busting on approval ─────────────────────────────────────

test('approving from the dashboard busts the cached sidebar badge', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \Illuminate\Support\Facades\Cache::put(
        \App\Helpers\MenuHelper::badgeCacheKey('workflow', $org->id),
        99,
        60,
    );

    $client = $this->createClient($org->id, ['status' => 'Pending']);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('dashboard.approve', ['type' => 'client_activate', 'id' => $client->id]))
        ->assertOk();

    expect(\Illuminate\Support\Facades\Cache::has(\App\Helpers\MenuHelper::badgeCacheKey('workflow', $org->id)))->toBeFalse();
});

test('approving from workflow queues busts the cached sidebar badge', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Badge', 'last_name' => 'Bust']);

    $billing = \App\Models\Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-BADGE-BUST',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 150,
        'status' => 'Pending',
    ]);

    config(['workflow_queues.demo_fallback' => false]);

    \Illuminate\Support\Facades\Cache::put(
        \App\Helpers\MenuHelper::badgeCacheKey('workflow', $org->id),
        99,
        60,
    );

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('workflow-queues.action', 'billing-'.$billing->id), [
            'queue_action' => 'approve',
            'approve_type' => 'billing',
            'approve_id' => $billing->id,
        ])
        ->assertOk();

    expect(\Illuminate\Support\Facades\Cache::has(\App\Helpers\MenuHelper::badgeCacheKey('workflow', $org->id)))->toBeFalse();
});

test('sidebar badges endpoint returns decremented count after workflow queue approve', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Sidebar', 'last_name' => 'Sync']);

    collect(['INV-SB-001', 'INV-SB-002'])->each(function (string $invoice) use ($org, $client) {
        \App\Models\Billing::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'invoice_number' => $invoice,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_amount' => 150,
            'status' => 'Pending',
        ]);
    });

    $billing = \App\Models\Billing::where('invoice_number', 'INV-SB-001')->firstOrFail();
    config(['workflow_queues.demo_fallback' => false]);

    $this->actingAsWithTwoFactor($admin);

    $this->getJson(route('sidebar.badges'))
        ->assertOk()
        ->assertJsonPath('/workflow-queues', 2);

    $approveResponse = $this->postJson(route('workflow-queues.action', 'billing-'.$billing->id), [
        'queue_action' => 'approve',
        'approve_type' => 'billing',
        'approve_id' => $billing->id,
    ])->assertOk();

    $badgeResponse = $this->getJson(route('sidebar.badges'))
        ->assertOk()
        ->assertJsonPath('/workflow-queues', 1);

    expect($badgeResponse->json('/workflow-queues'))
        ->toBe($approveResponse->json('approvalCount'))
        ->toBe(app(\App\Services\WorkflowQueueService::class)->approvalCount($org->id));
});

test('sidebar badges endpoint returns decremented count after dashboard approve', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Dash', 'last_name' => 'Sync']);

    collect(['INV-DS-001', 'INV-DS-002'])->each(function (string $invoice) use ($org, $client) {
        \App\Models\Billing::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'invoice_number' => $invoice,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_amount' => 150,
            'status' => 'Pending',
        ]);
    });

    $billing = \App\Models\Billing::where('invoice_number', 'INV-DS-001')->firstOrFail();
    config(['workflow_queues.demo_fallback' => false]);

    $this->actingAsWithTwoFactor($admin);

    $this->getJson(route('sidebar.badges'))
        ->assertOk()
        ->assertJsonPath('/workflow-queues', 2);

    $approveResponse = $this->postJson(route('dashboard.approve', ['type' => 'billing', 'id' => $billing->id]))
        ->assertOk();

    $badgeResponse = $this->getJson(route('sidebar.badges'))
        ->assertOk()
        ->assertJsonPath('/workflow-queues', 1);

    expect($badgeResponse->json('/workflow-queues'))
        ->toBe($approveResponse->json('approvalCount'))
        ->toBe(app(\App\Services\WorkflowQueueService::class)->approvalCount($org->id));
});

test('workflow queues and dashboard pages wire sidebar badge refresh hooks', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('workflow-queues'))
        ->assertOk()
        ->assertSee('sidebar-badges:refresh', false)
        ->assertSee('data-sidebar-badge="/workflow-queues"', false);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('sidebar-badges:refresh', false);
});

test('expiring authorization task auto-assigns via agent catalog bootstrap', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $michType = \App\Models\CoverageType::firstOrCreate(['name' => 'MICH']);

    $client->update(['coverage_type_id' => $michType->id]);

    $auth = \App\Models\CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'start_date' => today()->subMonths(6),
        'end_date' => today()->addDays(10),
        'status' => 'Active',
        'total_units' => 100,
        'needs_renewal' => true,
    ]);

    $auth->load('client');

    $task = app(\App\Services\TaskService::class)->createFromExpiringAuthorization($auth);

    expect($task->assignee_type)->toBe(\App\Models\Task::ASSIGNEE_AGENT)
        ->and($task->assignee_agent_id)->not->toBeNull()
        ->and($task->assigneeAgent?->slug)->toBe('authorizations');
});

test('expiring authorization task backfills assignee when legacy task was unassigned', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $auth = \App\Models\CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'start_date' => today()->subMonths(6),
        'end_date' => today()->addDays(10),
        'status' => 'Active',
        'total_units' => 100,
    ]);

    $auth->load('client');

    $legacy = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'related_type' => \App\Models\CareDetail::class,
        'related_id' => $auth->id,
        'source' => Task::SOURCE_SYSTEM,
        'status' => Task::STATUS_TODO,
        'title' => "Renew {$client->first_name} {$client->last_name}'s authorization",
        'priority' => Task::PRIORITY_HIGH,
        'due_date' => today()->addDays(3),
        'assignee_type' => Task::ASSIGNEE_USER,
        'client_id' => $client->id,
    ]);

    expect($legacy->assignee_agent_id)->toBeNull();

    $task = app(TaskService::class)->createFromExpiringAuthorization($auth);

    expect($task->id)->toBe($legacy->id)
        ->and($task->fresh()->assignee_type)->toBe(Task::ASSIGNEE_AGENT)
        ->and($task->fresh()->assignee_agent_id)->not->toBeNull()
        ->and($task->fresh()->assigneeAgent?->slug)->toBe('authorizations');
});

// ── A8 — auto agent assignment ────────────────────────────────────────────────

test('missed visit task auto-assigns to the communications agent', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id);

    $agent = AiAgent::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'slug' => 'communications',
        'name' => 'Communications Agent',
        'is_enabled' => true,
    ]);

    $task = app(TaskService::class)->createFromMissedVisit($schedule->fresh('client'));

    expect($task->assignee_type)->toBe(Task::ASSIGNEE_AGENT)
        ->and($task->assignee_agent_id)->toBe($agent->id);
});
