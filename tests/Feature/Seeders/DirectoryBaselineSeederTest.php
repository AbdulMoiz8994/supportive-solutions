<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\CoverageType;
use App\Models\IntegrationCredential;
use App\Services\GlobalSettingsService;
use App\Support\DirectoryMcoOptions;
use Database\Seeders\DirectoryBaselineSeeder;
use Database\Seeders\LookupTableSeeder;
use Database\Seeders\OrganizationSeeder;

test('directory baseline seeder populates ASWs, MCOs, state systems, and ASW fallback', function () {
    $this->seed([
        OrganizationSeeder::class,
        LookupTableSeeder::class,
        DirectoryBaselineSeeder::class,
    ]);

    expect(Contact::withoutGlobalScopes()->where('type', Contact::TYPE_AGENCY_STAFF)->count())->toBeGreaterThanOrEqual(3)
        ->and(Contact::withoutGlobalScopes()->where('type', Contact::TYPE_INSURANCE)->count())->toBeGreaterThanOrEqual(5)
        ->and(Contact::withoutGlobalScopes()->where('type', Contact::TYPE_OTHER)->where('name', 'ICHAT')->exists())->toBeTrue()
        ->and(IntegrationCredential::query()->count())->toBe(count(IntegrationCredential::supportedKeys()))
        ->and(app(GlobalSettingsService::class)->get('billing.default_asw_email'))->toBe('asw.homehelp@mdhhs.michigan.gov');

    expect(DirectoryMcoOptions::list())->not->toBe(DirectoryMcoOptions::FALLBACK);
});

test('client seeder assigns programs and directory links after baseline seed', function () {
    $this->seed([
        OrganizationSeeder::class,
        LookupTableSeeder::class,
        DirectoryBaselineSeeder::class,
        \Database\Seeders\ContactSeeder::class,
        \Database\Seeders\ClientSeeder::class,
    ]);

    $dhs = CoverageType::where('name', 'DHS Home Help')->first();
    $mich = CoverageType::where('name', 'MICH')->first();

    $john = Client::withoutGlobalScopes()->where('member_id', 'MD-100001')->first();
    $jane = Client::withoutGlobalScopes()->where('member_id', 'MD-100002')->first();

    expect($john)->not->toBeNull()
        ->and($john->coverage_type_id)->toBe($dhs?->id)
        ->and($john->program_label)->toBe('DHS')
        ->and($john->aswContact()?->email)->toBe('denise.carter@mdhhs.michigan.gov')
        ->and($jane->coverage_type_id)->toBe($mich?->id)
        ->and($jane->program_label)->toBe('MICH')
        ->and($jane->mco_name)->toBe('Molina Healthcare of Michigan')
        ->and($jane->caseCoordinator()?->email)->toBe('c.johnson@county.gov');

    $blankPrograms = Client::withoutGlobalScopes()
        ->get()
        ->filter(fn (Client $c) => $c->program_display === '—');

    expect($blankPrograms)->toHaveCount(0);
});
