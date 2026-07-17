<?php

use App\Jobs\SubmitPayrollClaimJob;
use App\Mail\PayrollApprovalNotification;
use App\Models\PayRecord;
use App\Models\PayrollClaim;
use App\Models\User;
use App\Services\Availity\AvailityClient;
use App\Services\Payroll\PayrollClaimService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'services.availity.env'            => 'demo',
        'services.availity.demo_key'       => 'demo-test-key',
        'services.availity.demo_secret'    => 'demo-test-secret',
        'services.availity.prod_key'       => 'prod-test-key',
        'services.availity.prod_secret'    => 'prod-test-secret',
        'services.availity.token_url'      => 'https://api.availity.com/v1/token',
        'services.availity.api_base_url'  => 'https://api.availity.com/availity/v1',
        'services.availity.base_url_demo'  => 'https://api.availity.com/availity/v1',
        'services.availity.base_url_prod'  => 'https://api.availity.com/availity/v1',
        'services.availity.request_type_code' => 'PRE_DETERMINATION',
        'services.availity.default_payer_id' => 'BCBSF',
        'payroll.accountant_email' => 'accountant@example.com',
    ]);
});

test('claim submission success stores reference and response via mocked Availity', function () {
    Http::fake([
        'https://api.availity.com/v1/token' => Http::response([
            'access_token' => 'payroll-test-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
        ]),
        'https://api.availity.com/availity/v1/professional-claims' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/AV-CLM-9001',
        ]),
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['member_id' => '4821234567', 'billing_rate' => 30]);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'program_tag' => 'MICH',
        'hours'       => 108,
    ]);

    $claim = app(PayrollClaimService::class)->submitForPayRecord($record);

    expect($claim->status)->toBe(PayrollClaim::STATUS_PENDING)
        ->and($claim->claim_reference_id)->toBe('AV-CLM-9001')
        ->and($claim->request_payload)->toBeArray()
        ->and($claim->response_payload)->toBeArray()
        ->and($claim->submitted_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer payroll-test-token')
        && $request->url() === 'https://api.availity.com/availity/v1/professional-claims');
});

test('claim failure is stored and job retries with exponential backoff', function () {
    Http::fake([
        'https://api.availity.com/v1/token' => Http::response([
            'access_token' => 'payroll-test-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
        ]),
        'https://api.availity.com/availity/v1/professional-claims' => Http::response(['message' => 'Invalid payload'], 422),
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['member_id' => '4821234567']);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['program_tag' => 'MICH']);

    $job = new SubmitPayrollClaimJob($record->id);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120, 300]);

    try {
        $job->handle(app(PayrollClaimService::class));
    } catch (\InvalidArgumentException) {
        // expected
    }

    $claim = PayrollClaim::withoutGlobalScopes()->where('pay_record_id', $record->id)->first();

    expect($claim)->not->toBeNull()
        ->and($claim->status)->toBe(PayrollClaim::STATUS_FAILED)
        ->and($claim->error_message)->toContain('Invalid payload');
});

test('payload validation rejects missing hours before Availity call', function () {
    Http::fake();

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'program_tag' => 'MICH',
        'hours'       => null,
        'gross'       => null,
    ]);

    $service = app(PayrollClaimService::class);

    expect(fn () => $service->buildAvailityPayload($record))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

test('payload maps T019 units and billing charges from verified hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['member_id' => '1234567890', 'billing_rate' => 30]);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Yousef', 'last_name' => 'Hassan']);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'program_tag' => 'MICH',
        'hours'       => 108,
    ]);

    $payload = app(PayrollClaimService::class)->buildAvailityPayload($record);

    expect($payload['serviceLines'][0]['procedureCode'])->toBe('T019')
        ->and($payload['serviceLines'][0]['units'])->toBe(432)
        ->and($payload['serviceLines'][0]['hours'])->toBe(108.0)
        ->and($payload['serviceLines'][0]['chargeAmount'])->toBe(3240.0)
        ->and($payload['patient']['memberId'])->toBe('1234567890')
        ->and($payload['renderingProvider']['firstName'])->toBe('Yousef');
});

test('payload includes agency billing provider npi and tax id from organization', function () {
    $org = $this->createOrganization([
        'agency_npi' => '1619784667',
        'tax_id_ein' => '331930284',
        'medicaid_provider_id' => '1619784667',
        'legal_business_name' => 'Supportive Solutions HomeCare LLC',
    ]);
    $client = $this->createClient($org->id, ['member_id' => '1234567890', 'billing_rate' => 30]);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'program_tag' => 'MICH',
        'hours'       => 10,
    ]);

    $payload = app(PayrollClaimService::class)->buildAvailityPayload($record);

    expect($payload['billingProvider']['npi'])->toBe('1619784667')
        ->and($payload['billingProvider']['taxId'])->toBe('331930284')
        ->and($payload['billingProvider']['organizationName'])->toBe('Supportive Solutions HomeCare LLC');
});

test('environment switching uses demo vs production api base and client id', function () {
    config(['services.availity.env' => 'demo']);
    $demoClient = app(AvailityClient::class);
    expect($demoClient->apiBaseUrl())->toBe('https://api.availity.com/availity/v1')
        ->and($demoClient->clientId())->toBe('demo-test-key')
        ->and($demoClient->scope())->toBe('healthcare-hipaa-transactions-demo healthcare-hipaa-transactions-demo-demo');

    config(['services.availity.env' => 'production']);
    $prodClient = app(AvailityClient::class);
    expect($prodClient->apiBaseUrl())->toBe('https://api.availity.com/availity/v1')
        ->and($prodClient->clientId())->toBe('prod-test-key')
        ->and($prodClient->scope())->toBe('healthcare-hipaa-transactions');
});

test('batch approval dispatches async payroll claim job and emails accountant', function () {
    Mail::fake();
    Queue::fake();

    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id, [
        'program_tag' => 'MICH',
    ]);

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), ['period' => '2026-05'])
        ->assertRedirect();

    $batch = \App\Models\PayrollBatch::withoutGlobalScopes()->first();

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.batch.approve', $batch))
        ->assertRedirect();

    Queue::assertPushed(SubmitPayrollClaimJob::class, fn (SubmitPayrollClaimJob $job) => $job->payRecordId === $record->id);

    Mail::assertSent(PayrollApprovalNotification::class, fn (PayrollApprovalNotification $mail) => $mail->hasTo('accountant@example.com'));
});

test('payroll detail shows claim status badge when claim exists', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['program_tag' => 'MICH']);

    PayrollClaim::withoutGlobalScopes()->create([
        'organization_id'    => $org->id,
        'pay_record_id'      => $record->id,
        'employee_id'        => $employee->id,
        'claim_reference_id' => 'AV-CLM-5555',
        'status'             => PayrollClaim::STATUS_SUBMITTED,
        'submitted_at'       => now(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertSee('Billing claim (Availity)')
        ->assertSee('Submitted')
        ->assertSee('AV-CLM-5555');
});

test('payroll detail shows failed claim error message', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['program_tag' => 'MICH']);

    PayrollClaim::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'pay_record_id'   => $record->id,
        'employee_id'     => $employee->id,
        'status'          => PayrollClaim::STATUS_FAILED,
        'error_message'   => 'Availity rejected the claim.',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertSee('Failed')
        ->assertSee('Availity rejected the claim.');
});
