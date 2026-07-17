<?php

use App\Models\BillingClaimAudit;
use App\Services\Availity\AvailityClaimStatusMapper;
use Carbon\Carbon;

test('mapper builds complete 276 inquiry fields from billing audit', function () {
    $org = test()->createOrganization([
        'agency_npi' => '1619784667',
        'legal_business_name' => 'Supportive Solutions HomeCare LLC',
    ]);
    $employee = test()->createEmployee($org->id, ['first_name' => 'Sam', 'last_name' => 'Caregiver']);
    $client = test()->createClient($org->id, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'member_id' => '4821234567',
        'dob' => Carbon::parse('1985-03-15'),
        'gender' => 'F',
    ]);

    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'employee_id' => $employee->id,
        'claim_number' => 'BCA-276-1',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_amount' => 3240.00,
    ]);

    $fields = app(AvailityClaimStatusMapper::class)->fromBillingClaimAudit($audit);

    expect($fields)->toMatchArray([
        'payer.id' => 'BCBSF',
        'submitter.lastName' => 'Supportive Solutions HomeCare LLC',
        'submitter.firstName' => 'Billing',
        'submitter.id' => '1619784667',
        'providers.lastName' => 'Caregiver',
        'providers.firstName' => 'Sam',
        'providers.npi' => '1619784667',
        'subscriber.memberId' => '4821234567',
        'subscriber.lastName' => 'Doe',
        'subscriber.firstName' => 'Jane',
        'patient.lastName' => 'Doe',
        'patient.firstName' => 'Jane',
        'patient.birthDate' => '1985-03-15',
        'patient.genderCode' => 'F',
        'patient.subscriberRelationshipCode' => '18',
        'fromDate' => '2024-05-01',
        'toDate' => '2024-05-31',
        'claimNumber' => 'BCA-276-1',
        'claimAmount' => '3240.00',
        'facilityTypeCode' => '12',
        'frequencyTypeCode' => '1',
    ]);
});

test('mapper prefers audit medicaid id over client member id', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '1111111111']);

    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'medicaid_id' => '9999999999',
        'claim_number' => 'BCA-MED-1',
    ]);

    $fields = app(AvailityClaimStatusMapper::class)->fromBillingClaimAudit($audit);

    expect($fields['subscriber.memberId'])->toBe('9999999999');
});

test('mapper falls back to generated claim number when claim number is missing', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id);

    $audit = new BillingClaimAudit([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_amount' => 1200,
        'submission_channel' => '837P - Availity',
    ]);
    $audit->setRelation('client', $client);
    $audit->id = 42;

    $fields = app(AvailityClaimStatusMapper::class)->fromBillingClaimAudit($audit);

    expect($fields['claimNumber'])->toBe('BCA-42');
});

test('mapper omits empty optional fields', function () {
    $org = test()->createOrganization(['agency_npi' => null, 'legal_business_name' => null]);
    $client = test()->createClient($org->id, ['member_id' => '123', 'last_name' => '', 'first_name' => '']);

    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'employee_id' => null,
        'claim_number' => 'BCA-EMPTY-1',
    ]);

    $fields = app(AvailityClaimStatusMapper::class)->fromBillingClaimAudit($audit);

    expect($fields)->not->toHaveKey('providers.npi')
        ->and($fields)->not->toHaveKey('subscriber.lastName')
        ->and($fields['subscriber.memberId'])->toBe('123');
});
