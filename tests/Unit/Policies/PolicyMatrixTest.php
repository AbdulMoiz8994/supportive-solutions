<?php

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Intake;
use App\Models\PayRecord;
use App\Models\User;
use App\Policies\BillingClaimAuditPolicy;
use App\Policies\ContactPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\IntakePolicy;
use App\Policies\PayrollPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('intake policy enforces organization boundaries', function () {
    $policy = new IntakePolicy();
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $intake = createTestIntake($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    expect($policy->view($adminA, Intake::withoutGlobalScopes()->find($intake->id)))->toBeTrue()
        ->and($policy->view($adminB, Intake::withoutGlobalScopes()->find($intake->id)))->toBeFalse()
        ->and($policy->convert($adminB, Intake::withoutGlobalScopes()->find($intake->id)))->toBeFalse();
});

test('employee policy restricts caregiver actions to caregivers', function () {
    $policy = new EmployeePolicy();
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $office = $this->createEmployee($org->id, ['position' => 'Case Manager']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    expect($policy->viewCaregiver($admin, Employee::withoutGlobalScopes()->find($caregiver->id)))->toBeTrue()
        ->and($policy->viewCaregiver($admin, Employee::withoutGlobalScopes()->find($office->id)))->toBeFalse();
});

test('billing claim audit policy requires view permission', function () {
    $policy = new BillingClaimAuditPolicy();
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = billingClaimAuditRecord($org->id, $client->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    expect($policy->view($admin, BillingClaimAudit::withoutGlobalScopes()->find($claim->id)))->toBeTrue()
        ->and($policy->view($employee, BillingClaimAudit::withoutGlobalScopes()->find($claim->id)))->toBeFalse();
});

test('payroll policy enforces organization and permission', function () {
    $policy = new PayrollPolicy();
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $employee = $this->createEmployee($orgA->id);
    $payRecord = payrollTestRecord($orgA->id, $employee->id, $client->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    expect($policy->view($adminA, PayRecord::withoutGlobalScopes()->find($payRecord->id)))->toBeTrue()
        ->and($policy->view($adminB, PayRecord::withoutGlobalScopes()->find($payRecord->id)))->toBeFalse();
});

test('contact policy allows office team within organization', function () {
    $policy = new ContactPolicy();
    $org = $this->createOrganization();
    $contact = $this->createContact($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    expect($policy->view($admin, Contact::withoutGlobalScopes()->find($contact->id)))->toBeTrue()
        ->and($policy->view($employee, Contact::withoutGlobalScopes()->find($contact->id)))->toBeFalse();
});

test('employee role cannot create clients via policy', function () {
    $policy = new \App\Policies\ClientPolicy();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->createOrganization()->id]);

    expect($policy->create($employee))->toBeFalse();
});
