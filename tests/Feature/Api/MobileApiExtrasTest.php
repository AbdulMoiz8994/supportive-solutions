<?php

use App\Events\MessageSent;
use App\Models\ComplianceForm;
use App\Models\User;
use App\Models\VisitTask;
use App\Services\Communication\SecureMessageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = $this->createOrganization();
    $this->user = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    $this->employee = $this->createEmployee($this->org->id, [
        'user_id' => $this->user->id,
        'first_name' => 'Robert',
        'last_name' => 'Nguyen',
        'hourly_wage' => 15.00,
        'address' => '742 Evergreen, Dearborn, MI',
    ]);
    $this->client = $this->createClient($this->org->id, [
        'first_name' => 'Maria',
        'last_name' => 'Hassan',
    ]);
    $this->employee->clients()->attach($this->client->id);
});

test('refresh rotates the token and revokes the old one', function () {
    $created = $this->user->createToken('api-token');
    $oldId = $created->accessToken->id;

    $this->withToken($created->plainTextToken)
        ->postJson('/api/refresh')
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

    // The old token row is revoked and exactly one (the fresh) token remains.
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldId]);
    expect($this->user->tokens()->count())->toBe(1);
});

test('me exposes avatar, initials and address', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.initials', 'RN')
        ->assertJsonPath('data.address', '742 Evergreen, Dearborn, MI')
        ->assertJsonPath('data.avatar_url', null);
});

test('dashboard aggregates today schedule, tasks and pay', function () {
    Sanctum::actingAs($this->user);

    $schedule = $this->createSchedule($this->org->id, $this->client->id, $this->employee->id, [
        'title' => 'Today Visit',
        'date' => today()->toDateString(),
    ]);

    VisitTask::create([
        'organization_id' => $this->org->id,
        'schedule_id' => $schedule->id,
        'client_id' => $this->client->id,
        'label' => 'Bathing Assistance',
        'is_completed' => true,
        'completed_at' => now(),
    ]);
    VisitTask::create([
        'organization_id' => $this->org->id,
        'schedule_id' => $schedule->id,
        'client_id' => $this->client->id,
        'label' => 'Medication Reminder',
    ]);

    payrollTestRecord($this->org->id, $this->employee->id, $this->client->id, [
        'period_key' => now()->format('Y-m'),
        'gross' => 1620.00,
        'hours' => 108,
    ]);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'data.today_schedule')
        ->assertJsonPath('data.tasks.done', 1)
        ->assertJsonPath('data.tasks.total', 2)
        ->assertJsonPath('data.pay.ytd_gross', fn ($v) => (float) $v === 1620.0)
        ->assertJsonPath('data.pay.paystub_count', 1);
});

test('earnings summary returns YTD, integrations and graph series', function () {
    Sanctum::actingAs($this->user);

    payrollTestRecord($this->org->id, $this->employee->id, $this->client->id, [
        'period_key' => now()->format('Y-m'),
        'gross' => 1620.00,
        'hours' => 108,
    ]);

    $this->getJson('/api/earnings/summary')
        ->assertOk()
        ->assertJsonPath('data.year_to_date.gross', fn ($v) => (float) $v === 1620.0)
        ->assertJsonPath('data.year_to_date.paystub_count', 1)
        ->assertJsonStructure(['data' => [
            'integrations' => ['quickbooks' => ['connected'], 'gusto' => ['ready']],
            'earnings_series',
            'hours_series',
        ]]);
});

test('pay detail returns a gross to net breakdown', function () {
    Sanctum::actingAs($this->user);

    $record = payrollTestRecord($this->org->id, $this->employee->id, $this->client->id, [
        'period_key' => now()->format('Y-m'),
        'gross' => 1000.00,
    ]);

    $this->getJson("/api/pay/{$record->id}")
        ->assertOk()
        ->assertJsonPath('data.breakdown.gross', fn ($v) => (float) $v === 1000.0)
        ->assertJsonPath('data.breakdown.fica', fn ($v) => (float) $v === 76.5) // 7.65% statutory
        ->assertJsonPath('data.breakdown.net', fn ($v) => (float) $v === 923.5)
        ->assertJsonStructure(['data' => ['visit_summary']]);
});

test('pay detail is only reachable for own records', function () {
    Sanctum::actingAs($this->user);

    $other = $this->createEmployee($this->org->id, ['first_name' => 'Other']);
    $record = payrollTestRecord($this->org->id, $other->id, $this->client->id);

    $this->getJson("/api/pay/{$record->id}")->assertForbidden();
});

test('schedule week groups visits into seven days', function () {
    Sanctum::actingAs($this->user);

    $this->createSchedule($this->org->id, $this->client->id, $this->employee->id, [
        'title' => 'Week Visit',
        'date' => today()->toDateString(),
    ]);

    $response = $this->getJson('/api/schedule/week')
        ->assertOk()
        ->assertJsonStructure(['data' => ['week_start', 'week_end', 'month', 'days']]);

    expect($response->json('data.days'))->toHaveCount(7);
});

test('care tasks can be created, listed and toggled', function () {
    Sanctum::actingAs($this->user);

    $schedule = $this->createSchedule($this->org->id, $this->client->id, $this->employee->id);

    $this->postJson("/api/visits/{$schedule->id}/tasks", [
        'label' => 'Bathing Assistance',
        'category' => 'Personal Care',
    ])->assertCreated();

    $this->getJson("/api/visits/{$schedule->id}/tasks")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_completed', false);

    $task = VisitTask::where('schedule_id', $schedule->id)->first();

    $this->postJson("/api/visits/{$schedule->id}/tasks/{$task->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_completed', true);
});

test('a caregiver cannot touch another caregivers visit tasks', function () {
    Sanctum::actingAs($this->user);

    $other = $this->createEmployee($this->org->id, ['first_name' => 'Other']);
    $schedule = $this->createSchedule($this->org->id, $this->client->id, $other->id);

    $this->getJson("/api/visits/{$schedule->id}/tasks")->assertForbidden();
});

test('compliance form can be listed, viewed with questions, and submitted', function () {
    Sanctum::actingAs($this->user);

    $form = ComplianceForm::create([
        'organization_id' => $this->org->id,
        'employee_id' => $this->employee->id,
        'client_id' => $this->client->id,
        'period' => now()->subMonth()->format('Y-m'),
        'status' => ComplianceForm::STATUS_DUE,
    ]);

    $this->getJson('/api/compliance-forms')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/compliance-forms/{$form->id}")
        ->assertOk()
        ->assertJsonPath('data.questions.0.key', 'provided_services');

    $this->postJson("/api/compliance-forms/{$form->id}/submit", [
        'answers' => [
            'provided_services' => true,
            'client_hospitalized' => false,
            'certify_accurate' => true,
        ],
        'additional_notes' => 'All good.',
        'signature' => base64_encode('fake-signature-png-bytes'),
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'Submitted')
        ->assertJsonPath('data.certification.provided_services', true);

    $this->getJson('/api/compliance-forms/history')
        ->assertOk()
        ->assertJsonPath('data.summary.submitted', 1);
});

test('compliance form is scoped to the owner', function () {
    Sanctum::actingAs($this->user);

    $other = $this->createEmployee($this->org->id, ['first_name' => 'Other']);
    $form = ComplianceForm::create([
        'organization_id' => $this->org->id,
        'employee_id' => $other->id,
        'period' => now()->format('Y-m'),
        'status' => ComplianceForm::STATUS_DUE,
    ]);

    $this->getJson("/api/compliance-forms/{$form->id}")->assertForbidden();
});

test('a document can be uploaded to an assigned client', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->user);

    $this->postJson('/api/documents', [
        'file' => UploadedFile::fake()->image('drivers-license.jpg'),
        'type' => 'ID',
        'client_id' => $this->client->id,
        'notes' => 'Front of license.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'ID')
        ->assertJsonPath('data.attached_to', 'Maria Hassan');

    $this->getJson('/api/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a document cannot be filed against an unassigned client', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->user);

    $stranger = $this->createClient($this->org->id, ['first_name' => 'Stranger']);

    $this->postJson('/api/documents', [
        'file' => UploadedFile::fake()->image('x.jpg'),
        'type' => 'ID',
        'client_id' => $stranger->id,
    ])->assertStatus(422);
});

test('sending a message broadcasts MessageSent', function () {
    Event::fake([MessageSent::class]);

    $office = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $this->org->id, 'name' => 'Salena James']);
    $service = app(SecureMessageService::class);

    $thread = $service->createThread($office, 'Setup help', 'How can we help?', [$this->user->id]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/conversations/{$thread->id}/messages", ['body' => 'Thanks!'])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($thread) {
        return $event->threadId === $thread->id
            && $event->payload['body'] === 'Thanks!';
    });
});
