<?php

use App\Models\CoverageType;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WorkflowQueueItem;
use App\Services\Communication\CommunicationInboundService;
use App\Services\Communication\WellnessCallService;
use App\Services\HHA\HHAExchangeClient;
use App\Services\HHA\HHASyncService;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedModuleBasics());

test('hha export visit posts to api when connected', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.scope' => 'write:aggregator',
        'hha.attestation_status' => 'approved',
        'hha.endpoints.visits' => '/api/v2/visits',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 1800]),
        'https://implementation.hhaexchange.com/api/v2/visits' => Http::response(['transactionId' => 'tx-visit-99'], 202),
    ]);

    $result = app(HHAExchangeClient::class)->exportVisit(['externalVisitId' => '1']);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('synced')
        ->and($result['transaction_id'])->toBe('tx-visit-99');
});

test('hha sync service marks schedule metadata after successful export', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.scope' => 'write:aggregator',
        'hha.attestation_status' => 'approved',
        'hha.provider_tax_id' => '331930284',
        'hha.office_npi' => '1619784667',
        'hha.payer_id' => 'MI73',
        'hha.endpoints.visits' => '/api/v2/visits',
        'hha.endpoints.caregivers' => '/api/v2/caregivers',
        'hha.endpoints.transactions' => '/api/v2/visits/transactions',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 1800]),
        'https://implementation.hhaexchange.com/api/v2/caregivers' => Http::response(['transactionId' => 'tx-cg'], 200),
        'https://implementation.hhaexchange.com/api/v2/visits' => Http::response(['transactionId' => 'tx-visit-55'], 202),
        'https://implementation.hhaexchange.com/api/v2/visits/transactions/tx-visit-55' => Http::response(['evvmsid' => 'ext-55'], 200),
    ]);

    $org = $this->createOrganization(['tax_id_ein' => '331930284', 'agency_npi' => '1619784667']);
    $client = $this->createClient($org->id, ['member_id' => 'MD-10001']);
    $caregiver = $this->createEmployee($org->id, [
        'date_of_birth' => '1990-01-01',
        'hire_date' => '2020-10-01',
    ]);

    $schedule = $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'evv_status' => true,
        'actual_clock_in' => now()->subHours(4),
        'actual_clock_out' => now(),
        'total_hours' => 4,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => [
            'client_home_lat' => 42.3314,
            'client_home_lng' => -83.0458,
            'location_overrides' => [
                ['approved' => true],
            ],
        ],
    ]);

    expect(app(HHASyncService::class)->syncVisit($schedule))->toBe('synced');

    $schedule->refresh();
    expect($schedule->metadata['hha_export']['status'])->toBe('synced')
        ->and($schedule->metadata['hha_export']['transaction_id'])->toBe('tx-visit-55')
        ->and($schedule->metadata['hha_export']['evvmsid'])->toBe('ext-55');
});

test('inbound communication needing reply appears in workflow human tasks', function () {
    $org = $this->createOrganization();

    $record = app(CommunicationInboundService::class)->recordRingCentralMessage([
        'id' => 'sms-100',
        'direction' => 'inbound',
        'type' => 'SMS',
        'from' => ['phoneNumber' => '+13135550100'],
        'to' => [['phoneNumber' => '+13135550999']],
        'subject' => 'Need a callback please',
        'text' => 'Please call me back about my authorization',
    ]);

    expect($record)->not->toBeNull();

    $task = WorkflowQueueItem::where('slug', 'comm-inbound-'.$record->id)->first();

    expect($task)->not->toBeNull()
        ->and($task->queue_type)->toBe(WorkflowQueueItem::TYPE_HUMAN_TASK)
        ->and($task->status)->toBe(WorkflowQueueItem::STATUS_PENDING)
        ->and($task->meta['title'])->toContain('Reply needed');
});

test('client wellness call can be triggered from compliance tab route', function () {
    config([
        'retell.api_key' => 'test-key',
        'retell.agent_id' => 'agent-1',
        'retell.from_number' => '+15550000000',
    ]);

    Http::fake([
        '*' => Http::response(['call_id' => 'call-manual-1'], 201),
    ]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['phone' => '+13135550111', 'status' => 'Active']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.wellness-call', $client->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Communication::withoutGlobalScopes()
        ->where('related_id', $client->id)
        ->whereJsonContains('metadata->wellness_call', true)
        ->count())->toBe(1);
});

test('intake eligibility uses availity live inquiry when configured', function () {
    config([
        'services.availity.env' => 'demo',
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response(['access_token' => 'availity-token', 'expires_in' => 3600]),
        'https://api.availity.com/availity/v1/coverages*' => Http::response([
            'coverages' => [['status' => 'active', 'payerName' => 'Aetna', 'planName' => 'MI Choice']],
        ]),
    ]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    CoverageType::firstOrCreate(['name' => 'DHS Home Help']);

    $this->actingAsWithTwoFactor($admin);

    $payload = $this->postJson(route('intakes.check-eligibility'), [
        'dob' => '1955-03-10',
        'member_id' => 'MD-10001',
        'mco_name' => '',
    ])->assertOk()->json();

    expect($payload['eligibility']['live'])->toBeTrue()
        ->and($payload['eligibility']['status'])->toBe(\App\Models\Intake::ELIGIBILITY_ELIGIBLE)
        ->and($payload['eligibility']['payer_name'])->toBe('Aetna');
});

test('monthly billing command auto-submits when automation setting is enabled', function () {
    config([
        'services.availity.env' => 'demo',
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response(['access_token' => 'availity-token', 'expires_in' => 3600]),
        'https://api.availity.com/availity/v1/professional-claims' => Http::response(['id' => 'claim-1', 'status' => 'submitted'], 201),
    ]);

    $org = $this->createOrganization();
    $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'billing_rate' => 30,
        'member_id' => 'MD-10001',
        'mco_name' => 'Aetna',
    ]);
    $caregiver = $this->createEmployee($org->id);

    $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => $lastMonth->copy()->subMonth(),
        'end_date' => $lastMonth->copy()->addMonths(3),
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => $lastMonth->copy()->addDays(5)->toDateString(),
        'total_hours' => 4,
        'evv_status' => true,
        'actual_clock_in' => $lastMonth->copy()->addDays(5)->setTime(8, 0),
        'actual_clock_out' => $lastMonth->copy()->addDays(5)->setTime(12, 0),
    ]);

    app(\App\Services\GlobalSettingsService::class)->update(['automation' => ['auto_submit_billing' => true]]);

    $this->artisan('billing:generate-claims', ['--org' => $org->id])->assertSuccessful();

    $claim = \App\Models\BillingClaimAudit::withoutGlobalScopes()->where('client_id', $client->id)->first();

    expect($claim)->not->toBeNull()
        ->and($claim->submitted_at)->not->toBeNull();
});
