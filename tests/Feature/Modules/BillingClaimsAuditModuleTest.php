<?php

use App\Models\BillingClaimAudit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedModuleBasics();
    Storage::fake('local');
});

test('guest cannot access billing claims audit', function () {
    $this->get(route('billing-claims-audit.index'))->assertRedirect(route('signin'));
});

test('billing claim show displays linked client name', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Billing', 'last_name' => 'Client']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id, ['claim_number' => '837P-LINK-001']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim))
        ->assertOk()
        ->assertSee('Billing Client')
        ->assertSee('837P-LINK-001');
});

test('billing claim belongs to client relationship', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = billingClaimAuditRecord($org->id, $client->id);

    expect($claim->client->id)->toBe($client->id)
        ->and($claim->organization_id)->toBe($org->id);
});

test('billing claim escalate updates audit status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.escalate', $claim))
        ->assertRedirect(route('billing-claims-audit.show', $claim));

    expect($claim->fresh()->audit_status)->toBe(BillingClaimAudit::AUDIT_ESCALATED);
});

test('billing claim refresh redirects with success', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.refresh', $claim))
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('success');
});

test('billing claim record eob validates paid amount', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.record-eob', $claim), [])
        ->assertSessionHasErrors(['paid_amount']);
});

test('billing claim record eob accepts valid payment data', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id, ['total_amount' => 3240]);
    $pdf = UploadedFile::fake()->create('eob.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.record-eob', $claim), [
            'paid_amount' => 3240,
            'payment_date' => '2026-05-15',
            'payer_reference' => 'EOB-12345',
            'eob_document' => $pdf,
        ])
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('success');
});

test('billing claim document download warns when file missing on disk', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'documents' => [
            ['name' => 'PA Letter', 'path' => 'billing/missing.pdf'],
        ],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.documents.download', [$claim, 0]))
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('warning');
});

test('billing claims generate submit requires edit permission', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $permission = \App\Models\Permission::where('slug', 'edit_billing_claims_audit')->first();
    $role = \App\Models\Role::where('name', User::ROLE_STAFF)->first();
    $role->permissions()->detach($permission->id);

    $this->actingAsWithTwoFactor($staff)
        ->post(route('billing-claims-audit.generate-submit'), ['period' => '2024-05'])
        ->assertForbidden();
});

test('billing claims audit update rejects invalid audit status via json', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->putJson(route('billing-claims-audit.update', $claim), ['audit_status' => 'invalid_status'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['audit_status']);
});

test('deleting client cascades removal of linked billing claims', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = billingClaimAuditRecord($org->id, $client->id);
    $claimId = $claim->id;

    $client->delete();

    expect(BillingClaimAudit::withoutGlobalScopes()->find($claimId))->toBeNull();
});

test('billing claims aging report loads without error for period with claims', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    billingClaimAuditRecord($org->id, $client->id, [
        'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
        'submitted_at' => now()->subDays(40),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.aging', ['period' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('Aging report');
});
