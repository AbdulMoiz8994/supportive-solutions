<?php

use App\Models\Client;
use App\Models\CoverageType;

beforeEach(fn () => seedModuleBasics());

function programClient(string $coverageTypeName): Client
{
    $org = test()->createOrganization();
    $type = CoverageType::where('name', $coverageTypeName)->firstOrFail();

    $client = test()->createClient($org->id, [
        'coverage_type_id' => $type->id,
        'status' => 'Active',
    ]);

    return $client->load('coverageType');
}

test('program_display resolves DHS from DHS Home Help coverage type', function () {
    $client = programClient('DHS Home Help');

    expect($client->program_display)->toBe('DHS')
        ->and($client->program_label)->toBe('DHS');
});

test('program_display resolves MICH from MICH coverage type', function () {
    $client = programClient('MICH');

    expect($client->program_display)->toBe('MICH')
        ->and($client->program_label)->toBe('MICH');
});

test('program_display resolves ICO distinctly while program_label buckets to MICH', function () {
    $client = programClient('ICO');

    expect($client->program_display)->toBe('ICO')
        ->and($client->program_label)->toBe('MICH');
});

test('program_display resolves DAAA distinctly while program_label buckets to MICH', function () {
    $client = programClient('DAAA');

    expect($client->program_display)->toBe('DAAA')
        ->and($client->program_label)->toBe('MICH');
});

test('program_display resolves Private Pay from Private Pay coverage type', function () {
    $client = programClient('Private Pay');

    expect($client->program_display)->toBe('Private Pay')
        ->and($client->program_label)->toBe('MICH');
});

test('program_display and program_label return em dash when coverage type is missing', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id, ['status' => 'Active']);
    $client->load('coverageType');

    expect($client->program_display)->toBe('—')
        ->and($client->program_label)->toBe('—');
});

test('program_display returns em dash after coverage type is deleted', function () {
    $org = test()->createOrganization();
    $type = CoverageType::create(['name' => 'Temporary Coverage']);
    $client = test()->createClient($org->id, [
        'status' => 'Active',
        'coverage_type_id' => $type->id,
    ]);

    $type->delete();
    $client->refresh()->load('coverageType');

    expect($client->coverage_type_id)->toBeNull()
        ->and($client->program_display)->toBe('—')
        ->and($client->program_label)->toBe('—');
});
