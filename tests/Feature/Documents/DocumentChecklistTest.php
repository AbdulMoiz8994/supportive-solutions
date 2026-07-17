<?php

use App\Services\DocumentChecklistService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->svc = app(DocumentChecklistService::class);
});

test('client checklist item auto-checks once a matching document is uploaded', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $before = collect($this->svc->forClient($client));
    expect($before->firstWhere('key', 'ssn')['checked'])->toBeFalse();

    $client->documents()->create([
        'name' => 'SSN Card', 'type' => 'identity', 'category' => 'ID',
        'path' => 'docs/ssn.pdf', 'organization_id' => $org->id,
    ]);
    $client->load('documents');

    $after = collect($this->svc->forClient($client));
    expect($after->firstWhere('key', 'ssn')['checked'])->toBeTrue();
    expect($after->firstWhere('key', 'ssn')['document_id'])->not->toBeNull();
    expect($after->firstWhere('key', 'dhs_390')['checked'])->toBeFalse();
});

test('caregiver checklist reflects an uploaded I-9 but leaves others unchecked', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id);
    $employee->documents()->create([
        'name' => 'I-9 Employment Eligibility', 'type' => 'HR', 'category' => 'HR',
        'path' => 'docs/i9.pdf', 'organization_id' => $org->id,
    ]);
    $employee->load('documents');

    $list = collect($this->svc->forCaregiver($employee));
    expect($list->firstWhere('key', 'i9')['checked'])->toBeTrue();
    expect($list->firstWhere('key', 'w4')['checked'])->toBeFalse();
});

test('summary reports completion progress', function () {
    $list = [
        ['key' => 'a', 'label' => 'A', 'checked' => true, 'document_id' => 1],
        ['key' => 'b', 'label' => 'B', 'checked' => false, 'document_id' => null],
    ];

    $summary = $this->svc->summary($list);
    expect($summary['done'])->toBe(1);
    expect($summary['total'])->toBe(2);
    expect($summary['percent'])->toBe(50);
    expect($summary['complete'])->toBeFalse();
});
