<?php

use App\Models\BillingClaimAudit;

test('usesAvaility returns true for MICH claims routed through Availity channel', function () {
    $audit = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'submission_channel' => '837P - Availity',
    ]);

    expect($audit->usesAvaility())->toBeTrue();
});

test('usesAvaility returns true when billing route contains availity', function () {
    $audit = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'submission_channel' => '837P',
        'billing_route' => 'availity_837p',
    ]);

    expect($audit->usesAvaility())->toBeTrue();
});

test('usesAvaility returns false for non-MICH programs', function () {
    $audit = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => '837P - Availity',
    ]);

    expect($audit->usesAvaility())->toBeFalse();
});

test('usesAvaility returns false for MICH claims without Availity routing', function () {
    $audit = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'submission_channel' => 'Paper',
        'billing_route' => 'manual',
    ]);

    expect($audit->usesAvaility())->toBeFalse();
});

test('availityStatusLabel humanizes stored status', function () {
    $audit = new BillingClaimAudit(['availity_status' => 'pending_payment']);

    expect($audit->availityStatusLabel())->toBe('Pending Payment');
});

test('availityStatusLabel returns null when status is empty', function () {
    $audit = new BillingClaimAudit(['availity_status' => null]);

    expect($audit->availityStatusLabel())->toBeNull();
});

test('syncClaimStatusFromBillingStatus maps pending payment to awaiting payment', function () {
    $audit = new BillingClaimAudit([
        'billing_status' => BillingClaimAudit::BILLING_PENDING_PAYMENT,
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
    ]);

    $audit->syncClaimStatusFromBillingStatus();

    expect($audit->claim_status)->toBe(BillingClaimAudit::STATUS_AWAITING_PAYMENT);
});

test('syncClaimStatusFromBillingStatus maps paid billing status to paid claim status', function () {
    $audit = new BillingClaimAudit([
        'billing_status' => BillingClaimAudit::BILLING_PAID,
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
    ]);

    $audit->syncClaimStatusFromBillingStatus();

    expect($audit->claim_status)->toBe(BillingClaimAudit::STATUS_PAID);
});
