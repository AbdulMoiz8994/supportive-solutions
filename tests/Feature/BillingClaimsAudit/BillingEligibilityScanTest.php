<?php

use App\Models\BillingClaimAudit;
use App\Models\CareDetail;
use App\Models\Schedule;
use App\Services\Billing\BillingEligibilityScanService;
use App\Services\Billing\BillingCycleAutomationService;
use App\Services\BillingClaimsAuditService;
use App\Jobs\GenerateMonthlyClaimsJob;
use Carbon\Carbon;

beforeEach(fn () => seedModuleBasics());

test('eligibility scan counts clients with clean visits and active authorization', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['billing_rate' => 30]);
    $caregiver = $this->createEmployee($org->id);
    $period = Carbon::parse('2026-05-01');

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-10',
        'total_hours' => 4,
        'evv_status' => true,
    ]);

    $scan = app(BillingEligibilityScanService::class);

    expect($scan->eligibleCount($org->id, $period))->toBe(1)
        ->and($scan->scanEligibleClients($org->id, $period)->first()['payable_hours'])->toBe(4.0);
});

test('eligibility scan excludes clients with expired authorization', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $period = Carbon::parse('2026-05-01');

    CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'start_date' => '2025-01-01',
        'end_date' => '2026-03-01',
        'status' => 'Active',
        'total_units' => 100,
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-10',
        'total_hours' => 4,
        'evv_status' => true,
    ]);

    expect(app(BillingEligibilityScanService::class)->eligibleCount($org->id, $period))->toBe(0);
});

test('eligibility scan excludes flagged visit hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $period = Carbon::parse('2026-05-01');

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-11',
        'total_hours' => 33.63,
        'evv_status' => true,
    ]);

    expect(app(BillingEligibilityScanService::class)->eligibleCount($org->id, $period))->toBe(0);
});

test('billing summary uses scanned eligible count before claims exist', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $period = Carbon::parse('2026-05-01');

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => '2026-05-10',
        'total_hours' => 6,
        'evv_status' => true,
    ]);

    $summary = app(BillingClaimsAuditService::class)->summaryForPeriod($org->id, $period);

    expect($summary['eligible_count'])->toBe(1)
        ->and($summary['auto_generated_count'])->toBe(0)
        ->and($summary['total_count'])->toBe(0);
});

test('automated billing run marks claims with null created_by and updates auto generated count', function () {
    $org = $this->createOrganization();
    $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['billing_rate' => 30, 'member_id' => '4821234567']);
    $caregiver = $this->createEmployee($org->id);
    $period = now()->subMonthNoOverflow()->startOfMonth();

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => $period->copy()->subMonth(),
        'end_date' => $period->copy()->addMonths(3),
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => $period->copy()->addDays(8)->toDateString(),
        'total_hours' => 5,
        'evv_status' => true,
        'actual_clock_in' => $period->copy()->addDays(8)->setTime(8, 0),
        'actual_clock_out' => $period->copy()->addDays(8)->setTime(13, 0),
    ]);

    availityTestConfig();
    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => \Illuminate\Support\Facades\Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/REF-AUTO-1',
        ]),
        'https://api.availity.com/availity/v1/professional-claims/*' => \Illuminate\Support\Facades\Http::response(['status' => 'submitted'], 200),
        'https://api.availity.com/availity/v1/claim-statuses' => \Illuminate\Support\Facades\Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'submitted']],
        ], 200),
    ]);

    app(\App\Services\GlobalSettingsService::class)->update(['automation' => ['auto_submit_billing' => true]]);

    app(BillingCycleAutomationService::class)->runForOrganization($org->id, $period);

    $claim = BillingClaimAudit::withoutGlobalScopes()->where('client_id', $client->id)->first();
    $summary = app(BillingClaimsAuditService::class)->summaryForPeriod($org->id, $period);

    expect($claim)->not->toBeNull()
        ->and($claim->created_by)->toBeNull()
        ->and($claim->submitted_at)->not->toBeNull()
        ->and($summary['eligible_count'])->toBe(1)
        ->and($summary['auto_generated_count'])->toBe(1);
});

test('generate monthly claims job delegates to billing cycle automation', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['billing_rate' => 30]);
    $caregiver = $this->createEmployee($org->id);
    $period = now()->subMonthNoOverflow()->startOfMonth();

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => $period->copy()->subMonth(),
        'end_date' => $period->copy()->addMonths(3),
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'date' => $period->copy()->addDays(4)->toDateString(),
        'total_hours' => 3,
        'evv_status' => true,
    ]);

    (new GenerateMonthlyClaimsJob($org->id, $period->format('Y-m')))->handle(app(BillingCycleAutomationService::class));

    expect(BillingClaimAudit::withoutGlobalScopes()->where('client_id', $client->id)->count())->toBe(1);
});
