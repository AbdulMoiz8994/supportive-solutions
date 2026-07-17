<?php

use App\Models\BackgroundCheck;
use App\Models\BillingClaimAudit;
use App\Models\CareDetail;
use App\Models\Communication;
use App\Models\ComplianceForm;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Communication\CommunicationInboundService;
use App\Services\Communication\WellnessCallService;
use App\Services\ScheduleCalendarService;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedModuleBasics());

test('billing:generate-claims creates claims from last month clean visits and reruns idempotently', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['billing_rate' => 30]);
    $caregiver = $this->createEmployee($org->id);

    $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => $lastMonth->copy()->subMonth(),
        'end_date' => $lastMonth->copy()->addMonths(3),
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => $lastMonth->copy()->addDays(9)->toDateString(),
        'total_hours' => 5,
        'evv_status' => true,
    ]);

    $this->artisan('billing:generate-claims', ['--org' => $org->id])->assertSuccessful();

    $claims = BillingClaimAudit::withoutGlobalScopes()->where('client_id', $client->id)->get();
    expect($claims)->toHaveCount(1)
        ->and((float) $claims->first()->total_hours)->toBe(5.0);

    // Rerunning the scheduled command must refresh, never duplicate.
    $this->artisan('billing:generate-claims', ['--org' => $org->id])->assertSuccessful();

    expect(BillingClaimAudit::withoutGlobalScopes()->where('client_id', $client->id)->count())->toBe(1);
});

test('background check batch stamps SAM and OIG monthly and never clears flagged rows', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    BackgroundCheck::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'type' => 'SAM',
        'status' => 'Flagged',
        'result' => 'Possible match — verify',
        'last_run' => today()->subMonths(2),
    ]);

    $this->artisan('background-checks:run-batch', ['--org' => $org->id])->assertSuccessful();

    $sam = BackgroundCheck::where('employee_id', $caregiver->id)->where('type', 'SAM')->first();
    $oig = BackgroundCheck::where('employee_id', $caregiver->id)->where('type', 'OIG')->first();

    // Flagged SAM row stays flagged for human review; OIG gets a fresh run.
    expect($sam->status)->toBe('Flagged')
        ->and($oig)->not->toBeNull()
        ->and($oig->status)->toBe('Clear')
        ->and($oig->last_run->isSameDay(today()))->toBeTrue();

    // Idempotent within the month — no duplicate rows.
    $this->artisan('background-checks:run-batch', ['--org' => $org->id])->assertSuccessful();

    expect(BackgroundCheck::where('employee_id', $caregiver->id)->count())->toBe(2);
});

test('wellness calls are placed once per client per month', function () {
    config([
        'retell.api_key' => 'test-key',
        'retell.agent_id' => 'agent-1',
        'retell.from_number' => '+15550000000',
    ]);

    Http::fake([
        'api.retellai.com/*' => Http::response(['call_id' => 'call-wellness-1'], 200),
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['phone' => '+15551234567']);
    $this->createClient($org->id, ['first_name' => 'No', 'last_name' => 'Phone', 'phone' => null]);

    $service = app(WellnessCallService::class);

    $first = $service->placeMonthlyCalls($org->id);
    expect($first['placed'])->toBe(1)
        ->and($first['no_phone'])->toBe(1);

    $log = Communication::withoutGlobalScopes()
        ->where('related_type', \App\Models\Client::class)
        ->where('related_id', $client->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->direction)->toBe(Communication::DIRECTION_OUTBOUND)
        ->and(data_get($log->metadata, 'wellness_call'))->toBeTrue();

    // Second run in the same month places nothing.
    $second = $service->placeMonthlyCalls($org->id);
    expect($second['placed'])->toBe(0)
        ->and($second['already_called'])->toBe(1);
});

test('completed wellness call without concern verifies the month compliance form', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    $form = ComplianceForm::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'client_id' => $client->id,
        'period' => now()->format('Y-m'),
        'status' => ComplianceForm::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    app(CommunicationInboundService::class)->recordFromRetellWebhook([
        'event' => 'call_ended',
        'call' => [
            'call_id' => 'call-verify-1',
            'transcript' => 'agent: Did the caregiver come this month? user: Yes, everything went well.',
            'retell_llm_dynamic_variables' => [
                'client_id' => (string) $client->id,
                'wellness_call' => true,
                'period' => now()->format('Y-m'),
            ],
        ],
    ]);

    expect($form->fresh()->status)->toBe(ComplianceForm::STATUS_VERIFIED)
        ->and($form->fresh()->wellness_call_note)->not->toBeNull();
});

test('wellness call with a concern leaves the compliance form submitted', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    $form = ComplianceForm::create([
        'organization_id' => $org->id,
        'employee_id' => $caregiver->id,
        'client_id' => $client->id,
        'period' => now()->format('Y-m'),
        'status' => ComplianceForm::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $record = app(CommunicationInboundService::class)->recordFromRetellWebhook([
        'event' => 'call_ended',
        'call' => [
            'call_id' => 'call-concern-1',
            'transcript' => 'user: I had a fall last week and went to the hospital.',
            'retell_llm_dynamic_variables' => [
                'client_id' => (string) $client->id,
                'wellness_call' => true,
                'period' => now()->format('Y-m'),
            ],
        ],
    ]);

    expect($form->fresh()->status)->toBe(ComplianceForm::STATUS_SUBMITTED)
        ->and(data_get($record->metadata, 'handled_by'))->toBe('concern');
});

test('calendar collapses seeded duplicates of generated system events', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $firstOfMonth = now()->startOfMonth();

    // Seeded schedule row duplicating the generated "SAM + OIG batch" event.
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'title' => 'SAM + OIG batch',
        'date' => $firstOfMonth->toDateString(),
        'start_time' => null,
        'end_time' => null,
        'start_at' => null,
        'end_at' => null,
    ]);

    $this->actingAsWithTwoFactor($admin);

    $events = app(ScheduleCalendarService::class)->collectEvents(
        $firstOfMonth->copy(),
        $firstOfMonth->copy()->endOfMonth(),
        [],
    );

    $samEvents = $events->filter(fn (array $event) => $event['title'] === 'SAM + OIG batch'
        && $event['date'] === $firstOfMonth->toDateString());

    expect($samEvents)->toHaveCount(1)
        ->and($samEvents->first()['schedule_id'])->not->toBeNull();
});

test('calendar collapses seeded duplicates of 45-day determination events', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Maria', 'last_name' => 'Hassan']);
    $caregiver = $this->createEmployee($org->id);
    $endDate = today()->addDays(10);

    CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T019',
        'start_date' => today()->subMonths(5),
        'end_date' => $endDate,
        'total_units' => 112,
        'status' => 'Active',
    ]);

    $title = '45-day determination — Maria Hassan';

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'title' => $title,
        'date' => $endDate->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'start_at' => $endDate->format('Y-m-d').' 09:00:00',
        'end_at' => $endDate->format('Y-m-d').' 10:00:00',
        'event_type' => \App\Models\Schedule::EVENT_REASSESSMENT,
    ]);

    $events = app(ScheduleCalendarService::class)->collectEvents(
        today(),
        today()->addMonth(),
        [],
    );

    $determinationEvents = $events->filter(fn (array $event) => $event['title'] === $title
        && $event['date'] === $endDate->toDateString());

    expect($determinationEvents)->toHaveCount(1)
        ->and($determinationEvents->first()['schedule_id'])->not->toBeNull();
});

test('calendar collapses multiple seeded 45-day determination rows on the same day', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Omar', 'last_name' => 'Farouk']);
    $caregiver = $this->createEmployee($org->id);
    $date = today()->addDays(5);
    $title = '45-day determination — Omar Farouk';

    foreach (['09:00:00', '11:00:00', '14:00:00'] as $startTime) {
        $this->createSchedule($org->id, $client->id, $caregiver->id, [
            'title' => $title,
            'date' => $date->toDateString(),
            'start_time' => $startTime,
            'end_time' => '15:00:00',
            'start_at' => $date->format('Y-m-d').' '.$startTime,
            'end_at' => $date->format('Y-m-d').' 15:00:00',
            'event_type' => \App\Models\Schedule::EVENT_REASSESSMENT,
        ]);
    }

    $events = app(ScheduleCalendarService::class)->collectEvents(
        today(),
        today()->addMonth(),
        [],
    );

    $determinationEvents = $events->filter(fn (array $event) => $event['title'] === $title
        && $event['date'] === $date->toDateString());

    expect($determinationEvents)->toHaveCount(1);
});

test('month grid caps visible events per day and reports overflow', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id);
    $date = today()->addDays(2);

    foreach (range(1, 5) as $index) {
        $client = $this->createClient($org->id, ['first_name' => "Client{$index}", 'last_name' => 'Test']);
        $this->createSchedule($org->id, $client->id, $caregiver->id, [
            'title' => "Visit {$index}",
            'date' => $date->toDateString(),
            'start_time' => sprintf('%02d:00:00', 7 + $index),
            'end_time' => sprintf('%02d:00:00', 8 + $index),
            'start_at' => $date->format('Y-m-d').' '.sprintf('%02d:00:00', 7 + $index),
            'end_at' => $date->format('Y-m-d').' '.sprintf('%02d:00:00', 8 + $index),
        ]);
    }

    $pageData = app(ScheduleCalendarService::class)->buildPageData([
        'view' => 'month',
        'month' => $date->month,
        'year' => $date->year,
    ], false);

    $day = collect($pageData['calendarDays'])->firstWhere('date', $date->toDateString());

    expect($day)->not->toBeNull()
        ->and($day['events'])->toHaveCount(ScheduleCalendarService::VISIBLE_EVENTS_PER_DAY)
        ->and($day['overflow'])->toBe(2)
        ->and($day['all_events'])->toHaveCount(5);
});

test('marking an inbound communication handled clears it from needs review', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $communication = Communication::create([
        'organization_id' => $org->id,
        'related_type' => \App\Models\Client::class,
        'related_id' => $client->id,
        'channel' => Communication::CHANNEL_SMS,
        'direction' => Communication::DIRECTION_INBOUND,
        'subject' => 'Inbound SMS',
        'body' => 'Please call me back about my schedule.',
        'status' => Communication::STATUS_RECEIVED,
        'sent_at' => now(),
        'metadata' => ['handled_by' => 'needs_review', 'party_name' => 'Test Client'],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.mark-handled', $communication->id))
        ->assertRedirect();

    $fresh = $communication->fresh();

    expect(data_get($fresh->metadata, 'handled_by'))->toBe('staff')
        ->and($fresh->status)->toBe(Communication::STATUS_READ)
        ->and(data_get($fresh->metadata, 'handled_at'))->not->toBeNull();
});
