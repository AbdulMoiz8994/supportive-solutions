<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function payrollVerifiedComplianceForm(int $orgId, int $employeeId, int $clientId, array $attributes = []): \App\Models\ComplianceForm
{
    return \App\Models\ComplianceForm::withoutGlobalScopes()->create(array_merge([
        'organization_id'  => $orgId,
        'employee_id'      => $employeeId,
        'client_id'        => $clientId,
        'period'           => '2026-05',
        'period_label'     => 'May 2026',
        'status'           => \App\Models\ComplianceForm::STATUS_VERIFIED,
        'delivered_hours'  => 108,
        'authorized_hours' => 120,
        'submitted_at'     => now()->subDays(15),
        'service_start'    => '2026-05-01',
        'service_end'      => '2026-05-31',
    ], $attributes));
}

function payrollReadyTestRecord(int $orgId, int $employeeId, int $clientId, array $recordAttributes = [], array $formAttributes = []): \App\Models\PayRecord
{
    $form = payrollVerifiedComplianceForm($orgId, $employeeId, $clientId, $formAttributes);

    return payrollTestRecord($orgId, $employeeId, $clientId, array_merge([
        'status'             => \App\Models\PayRecord::STATUS_READY,
        'compliance_form_id' => $form->id,
    ], $recordAttributes));
}

function payrollTestRecord(int $orgId, int $employeeId, int $clientId, array $attributes = []): \App\Models\PayRecord
{
    return \App\Models\PayRecord::withoutGlobalScopes()->forceCreate(array_merge([
        'organization_id' => $orgId,
        'employee_id'     => $employeeId,
        'client_id'       => $clientId,
        'period'          => 'May 2026',
        'period_key'      => '2026-05',
        'hours'           => 108,
        'rate'            => 15.00,
        'gross'           => 1620.00,
        'status'          => \App\Models\PayRecord::STATUS_READY,
        'caregiver_type'  => \App\Models\PayRecord::CAREGIVER_FAMILY,
        'program_tag'     => 'MICH',
    ], $attributes));
}

function billingActiveAuthorization(int $orgId, int $clientId, array $attributes = []): \App\Models\CareDetail
{
    return \App\Models\CareDetail::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $orgId,
        'client_id' => $clientId,
        'billing_code' => 'T019',
        'start_date' => now()->subMonths(2)->startOfMonth(),
        'end_date' => now()->addMonths(4)->endOfMonth(),
        'total_units' => 432,
        'status' => 'Active',
    ], $attributes));
}

function billingClaimAuditRecord(int $orgId, int $clientId, array $attributes = []): \App\Models\BillingClaimAudit
{
    return \App\Models\BillingClaimAudit::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $orgId,
        'client_id' => $clientId,
        'claim_number' => '837P-TEST-'.uniqid(),
        'program_type' => \App\Models\BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 108.0,
        'service_code' => 'T019',
        'hourly_rate' => 30.00,
        'total_amount' => 3240.00,
        'submission_channel' => '837P - Availity',
        'claim_status' => \App\Models\BillingClaimAudit::STATUS_SUBMITTED,
        'billing_status' => \App\Models\BillingClaimAudit::BILLING_SUBMITTED,
        'submitted_at' => now(),
    ], $attributes));
}

function availityTestConfig(array $overrides = []): void
{
    config(array_merge([
        'services.availity.env' => 'demo',
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
        'services.availity.token_url' => 'https://api.availity.com/v1/token',
        'services.availity.api_base_url' => 'https://api.availity.com/availity/v1',
        'services.availity.default_payer_id' => 'BCBSF',
        'services.availity.scope_demo' => 'healthcare-hipaa-transactions-demo',
        'services.availity.request_type_code' => 'PRE_DETERMINATION',
        'services.availity.place_of_service_code' => '12',
        'services.availity.patient_relationship_code' => '18',
    ], $overrides));
}

function availityHttpFake(array $responses = []): void
{
    \Illuminate\Support\Facades\Http::fake(array_merge([
        'https://api.availity.com/v1/token' => \Illuminate\Support\Facades\Http::response([
            'access_token' => 'test-token',
            'expires_in' => 300,
        ]),
    ], $responses));
}

function mockGoogleWorkspaceForBilling(): void
{
    config(['billing_claims_audit.default_asw_email' => 'asw@mdhhs.example.com']);

    test()->mock(\App\Services\Communication\CommunicationIntegrationStatusService::class, function ($mock) {
        $mock->shouldReceive('googleReady')->andReturn(true);
        $mock->shouldReceive('ringCentralReady')->andReturn(false);
        $mock->shouldReceive('forCompose')->andReturn([
            'google' => true,
            'ringcentral' => false,
            'ringcentral_sms' => false,
            'ringcentral_sms_message' => '',
            'ringcentral_fax' => false,
            'ringcentral_fax_message' => '',
            'google_message' => 'Google Workspace connected.',
            'ringcentral_message' => '',
        ]);
    });

    test()->mock(\App\Services\Integrations\GoogleWorkspaceClient::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('sendEmail')->andReturn([
            'success' => true,
            'provider_message_id' => 'gmail-test-1',
            'failure_reason' => null,
        ]);
    });
}

/**
 * Seed roles/permissions and lookup tables required by most module tests.
 */
function seedModuleBasics(): void
{
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    test()->seed(\Database\Seeders\LookupTableSeeder::class);
}

function intakePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Lead',
        'last_name' => 'Prospect',
        'phone' => '(313) 555-0199',
        'email' => 'lead@example.com',
        'source' => 'Referral',
    ], $overrides);
}

function createTestIntake(int $organizationId, array $attributes = []): \App\Models\Intake
{
    $status = \App\Models\Status::where('entity_type', 'Intake')->where('name', 'New Lead')->first();

    return \App\Models\Intake::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $organizationId,
        'first_name' => 'Pipeline',
        'last_name' => 'Lead',
        'phone' => '(313) 555-0100',
        'status' => 'New',
        'status_id' => $status?->id,
    ], $attributes));
}

function createCommunicationTemplate(int $organizationId, array $attributes = []): \App\Models\CommunicationTemplate
{
    return \App\Models\CommunicationTemplate::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $organizationId,
        'name' => 'Test Communication Template',
        'slug' => 'test-communication-template-'.uniqid(),
        'channel' => \App\Models\CommunicationTemplate::CHANNEL_EMAIL,
        'subject' => 'Hello {{ client.first_name }}',
        'body' => 'Message for {{ client.first_name }} {{ client.last_name }}',
        'recipient_strategy' => \App\Models\CommunicationTemplate::STRATEGY_MANUAL,
        'default_recipient' => 'manual@example.com',
        'is_active' => true,
    ], $attributes));
}

function stubRingCentralCredentials(): void
{
    \App\Models\IntegrationCredential::query()->updateOrCreate(
        ['key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL],
        [
            'api_key' => 'client-id',
            'password' => 'client-secret',
            'metadata' => ['server_url' => 'https://platform.ringcentral.com', 'from_number' => '+15550001111'],
        ]
    );

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    \Illuminate\Support\Facades\Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => \Illuminate\Support\Facades\Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
            'scope' => 'SMS Fax ReadAccounts',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~' => \Illuminate\Support\Facades\Http::response([
            'extensionNumber' => '101',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/phone-number' => \Illuminate\Support\Facades\Http::response([
            'records' => [
                ['phoneNumber' => '+15550001111', 'features' => ['SmsSender']],
            ],
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/fax' => \Illuminate\Support\Facades\Http::response([
            'id' => 'fax-123',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/sms' => \Illuminate\Support\Facades\Http::response([
            'id' => 'sms-123',
        ]),
    ]);
}

function employeePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Office',
        'last_name' => 'Staff',
        'email' => fake()->unique()->safeEmail(),
        'phone' => '(313) 555-0144',
        'position' => 'Case Manager',
    ], $overrides);
}
