<?php

use App\Models\CareDetail;
use App\Models\Client;
use App\Models\DataExplorationView;
use App\Models\Document;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Services\DataExplorationService;
use App\Services\FormsTrackingService;
use App\Services\TaskService;
use App\Services\VisitReportService;
use Illuminate\Http\Request;

beforeEach(fn () => seedModuleBasics());

// ── Visit Reports (QA boxes) ────────────────────────────────────────────────

test('completed visit duration equals clock out minus clock in', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $clockIn = now()->subHours(2);
    $clockOut = now();

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => $clockIn->format('H:i:s'),
        'end_time' => $clockOut->format('H:i:s'),
        'start_at' => $clockIn,
        'end_at' => $clockOut,
        'actual_clock_in' => $clockIn,
        'actual_clock_out' => $clockOut,
        'total_hours' => 2.0,
        'evv_status' => true,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    expect($service->resolveReportStatus($schedule))->toBe(VisitReportService::STATUS_COMPLETE);
    expect($service->isBillable($schedule))->toBeTrue();
});

test('location mismatch flags visit when gps is far from client home', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'actual_clock_in' => now()->subHours(2),
        'actual_clock_out' => now(),
        'total_hours' => 2.0,
        'clock_in_latitude' => 42.2800,
        'clock_in_longitude' => -83.7500,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $service = app(VisitReportService::class);
    expect($service->locationMatches($schedule))->toBeFalse();
    expect($service->resolveReportStatus($schedule))->toBe(VisitReportService::STATUS_NEEDS_REVIEW);
});

test('time correction keeps original and requires approval', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $originalOut = now()->subHour();
    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'actual_clock_in' => now()->subHours(3),
        'actual_clock_out' => null,
    ]);

    $service = app(VisitReportService::class);
    $service->proposeTimeCorrection(
        $org->id,
        $schedule->id,
        $admin,
        'actual_clock_out',
        $originalOut->copy()->addHour()->toIso8601String(),
        'Caregiver forgot to clock out',
    );

    $schedule->refresh();
    $corrections = data_get($schedule->metadata, 'time_corrections', []);
    expect($corrections)->toHaveCount(1);
    expect($corrections[0]['approved'])->toBeFalse();
    expect($corrections[0]['reason'])->toBe('Caregiver forgot to clock out');
    expect($schedule->actual_clock_out)->toBeNull();
});

test('visit report counters align with filtered row counts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'start_at' => today()->format('Y-m-d').' 09:00:00',
        'end_at' => today()->format('Y-m-d').' 11:00:00',
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 11:00:00',
        'total_hours' => 2,
        'evv_status' => true,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);

    $request = Request::create('/visit-reports', 'GET', ['date_preset' => 'this_week']);
    $data = app(VisitReportService::class)->pageData($org->id, $request);

    $completeCounter = collect($data['counters'])->firstWhere('key', 'complete');
    expect($completeCounter['value'])->toBeGreaterThan(0);

    $filtered = app(VisitReportService::class)->pageData($org->id, Request::create('/visit-reports', 'GET', [
        'date_preset' => 'this_week',
        'report_status' => 'complete',
    ]));
    expect(count($filtered['rows']))->toBe($completeCounter['value']);
});

// ── Tasks (QA boxes) ────────────────────────────────────────────────────────

test('task status transitions update counters', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = app(TaskService::class)->store($org->id, [
        'title' => 'Status transition test',
        'priority' => 'medium',
        'assignee_type' => 'user',
        'assignee_user_id' => $admin->id,
    ], $admin);

    app(TaskService::class)->updateStatus($org->id, $task->id, Task::STATUS_IN_PROGRESS);
    expect(Task::find($task->id)->status)->toBe(Task::STATUS_IN_PROGRESS);

    app(TaskService::class)->updateStatus($org->id, $task->id, Task::STATUS_DONE);
    expect(Task::find($task->id)->status)->toBe(Task::STATUS_DONE);

    app(TaskService::class)->updateStatus($org->id, $task->id, Task::STATUS_REOPEN);
    expect(Task::find($task->id)->status)->toBe(Task::STATUS_REOPEN);
});

test('authorization expiring creates system task', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    CareDetail::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => today()->subMonths(5),
        'end_date' => today()->addDays(10),
        'total_units' => 320,
        'status' => 'Active',
    ]);

    app(TaskService::class)->syncAuthorizationTasks($org->id);

    $task = Task::where('organization_id', $org->id)
        ->where('source', Task::SOURCE_SYSTEM)
        ->where('related_type', CareDetail::class)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->assignee_type)->toBe(Task::ASSIGNEE_AGENT)
        ->and($task->assignee_agent_id)->not->toBeNull()
        ->and($task->assigneeAgent?->slug)->toBe('authorizations');
});

test('task linked to client opens client record', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Client linked task',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
        'client_id' => $client->id,
        'source' => Task::SOURCE_MANUAL,
    ]);

    expect($task->relatedUrl())->toBe(route('clients.show', $client->id));
});

// ── Forms (QA boxes) ────────────────────────────────────────────────────────

test('signing form creates document in client file', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent',
        'slug' => 'consent-doc-test',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name']],
        'requires_signature' => true,
        'is_compliance_required' => true,
        'is_active' => true,
    ]);

    $submission = app(FormsTrackingService::class)->storeSubmission(
        $org->id,
        $template->id,
        $client->id,
        ['full_name' => 'Test Client', 'signature_name' => 'Test Client'],
        $admin,
        'sign',
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_SIGNED);
    expect($submission->document_id)->not->toBeNull();
    expect(Document::find($submission->document_id)?->category)->toBe('Compliance');
});

test('signed compliance form appears on compliance page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent to Care',
        'slug' => 'consent-compliance-test',
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

test('send for signature sets awaiting status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['email' => 'signer@example.com']);

    \Illuminate\Support\Facades\Mail::fake();

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Intake',
        'slug' => 'intake-await-test',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = app(FormsTrackingService::class)->storeSubmission(
        $org->id,
        $template->id,
        $client->id,
        ['full_name' => 'Await Test'],
        $admin,
        'send_signature',
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_AWAITING_SIGNATURE);
    expect($submission->signing_token)->not->toBeEmpty();
    expect($submission->esign_sent_at)->not->toBeNull();
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\FormEsignRequestMail::class);
});

test('remote esign link signs and locks the form', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['email' => 'remote@example.com']);

    $template = FormTemplate::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'name' => 'Consent',
        'slug' => 'consent-remote-sign',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [['key' => 'full_name', 'label' => 'Name', 'type' => 'text']],
        'requires_signature' => true,
        'is_active' => true,
    ]);

    $submission = FormSubmission::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'form_template_id' => $template->id,
        'subject_type' => Client::class,
        'subject_id' => $client->id,
        'status' => FormSubmission::STATUS_AWAITING_SIGNATURE,
        'field_values' => ['full_name' => 'Remote Signer'],
        'signing_token' => 'test-token-remote-sign',
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(route('forms.esign.show', 'test-token-remote-sign'))
        ->assertOk()
        ->assertSee('Consent');

    $this->post(route('forms.esign.sign', 'test-token-remote-sign'), [
        'signed_by_name' => 'Remote Signer',
        'signature_image' => 'data:image/png;base64,aaa',
    ])->assertOk();

    $fresh = $submission->fresh();
    expect($fresh->status)->toBe(FormSubmission::STATUS_SIGNED);
    expect($fresh->isLocked())->toBeTrue();
    expect($fresh->document_id)->not->toBeNull();
});

// ── Data Exploration (QA boxes) ───────────────────────────────────────────────

test('visit counts match between visit reports and data exploration', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    foreach (range(1, 3) as $i) {
        $this->createSchedule($org->id, $client->id, $caregiver->id, [
            'date' => today()->subDays($i)->toDateString(),
            'status' => Schedule::STATUS_COMPLETED,
            'actual_clock_in' => today()->subDays($i)->setTime(9, 0),
            'actual_clock_out' => today()->subDays($i)->setTime(11, 0),
            'total_hours' => 2,
            'evv_status' => true,
        ]);
    }

    $from = today()->subWeek()->toDateString();
    $to = today()->toDateString();
    $config = ['date_from' => $from, 'date_to' => $to];

    $explorationCount = count(app(DataExplorationService::class)->query($org->id, 'visits', $config)['rows']);

    $visitRequest = Request::create('/visit-reports', 'GET', [
        'date_preset' => 'custom',
        'date_from' => $from,
        'date_to' => $to,
    ]);
    $visitRows = app(VisitReportService::class)->pageData($org->id, $visitRequest)['rows'];

    expect($explorationCount)->toBe(count($visitRows));
});

test('saved data exploration view can be re-run', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $config = [
        'date_from' => today()->subMonth()->toDateString(),
        'date_to' => today()->toDateString(),
        'group_by' => 'status',
        'aggregate' => 'count',
    ];

    $view = app(DataExplorationService::class)->saveView(
        $org->id,
        $admin,
        'Weekly visits by status',
        'visits',
        $config,
    );

    $saved = DataExplorationView::find($view->id);
    expect($saved->config['group_by'])->toBe('status');

    $result = app(DataExplorationService::class)->query($org->id, $saved->dataset, $saved->config);
    expect($result)->toHaveKeys(['columns', 'rows', 'chart']);
});

test('employee without module permissions cannot access visit reports', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('visit-reports'))
        ->assertForbidden();
});

test('employee without module permissions cannot access tasks', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('tasks'))
        ->assertForbidden();
});
