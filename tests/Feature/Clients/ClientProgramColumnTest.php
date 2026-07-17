<?php

use App\Models\CareDetail;
use App\Models\Client;
use App\Models\CoverageType;
use App\Models\User;
use App\Services\RegistryMetricsService;

beforeEach(fn () => seedModuleBasics());

function coverageType(string $name): CoverageType
{
    return CoverageType::where('name', $name)->firstOrFail();
}

function clientForProgramColumn(int $orgId, string $firstName, string $lastName, ?string $coverageTypeName = null, array $extra = []): Client
{
    $attributes = array_merge([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'status' => 'Active',
    ], $extra);

    if ($coverageTypeName !== null) {
        $attributes['coverage_type_id'] = coverageType($coverageTypeName)->id;
    }

    return Client::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $orgId,
    ], $attributes));
}

test('client registry rows expose program_display for DHS, MICH, ICO, DAAA, and Private Pay', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    clientForProgramColumn($org->id, 'Dhs', 'Registry', 'DHS Home Help');
    clientForProgramColumn($org->id, 'Mich', 'Registry', 'MICH');
    clientForProgramColumn($org->id, 'Ico', 'Registry', 'ICO');
    clientForProgramColumn($org->id, 'Daaa', 'Registry', 'DAAA');
    clientForProgramColumn($org->id, 'Private', 'Registry', 'Private Pay');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertViewHas('rows', function ($rows) {
            $byName = collect($rows)->keyBy('name');

            expect($byName['Dhs Registry']['program_display'])->toBe('DHS')
                ->and($byName['Mich Registry']['program_display'])->toBe('MICH')
                ->and($byName['Ico Registry']['program_display'])->toBe('ICO')
                ->and($byName['Daaa Registry']['program_display'])->toBe('DAAA')
                ->and($byName['Private Registry']['program_display'])->toBe('Private Pay');

            return true;
        });
});

test('client registry rows include coarse program bucket for filters and KPIs', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    clientForProgramColumn($org->id, 'Dhs', 'Filter', 'DHS Home Help');
    clientForProgramColumn($org->id, 'Ico', 'Filter', 'ICO');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertViewHas('rows', function ($rows) {
            $byName = collect($rows)->keyBy('name');

            expect($byName['Dhs Filter']['program'])->toBe('DHS')
                ->and($byName['Ico Filter']['program'])->toBe('MICH');

            return true;
        });
});

test('client registry renders em dash program when coverage type is not assigned', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    clientForProgramColumn($org->id, 'Blank', 'Program');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('name', 'Blank Program');

            expect($row['program_display'])->toBe('—')
                ->and($row['program'])->toBe('—');

            return true;
        });
});

test('client registry header program subcounts match active row program buckets', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    clientForProgramColumn($org->id, 'Active', 'DhsOne', 'DHS Home Help');
    clientForProgramColumn($org->id, 'Active', 'DhsTwo', 'DHS Home Help');
    clientForProgramColumn($org->id, 'Active', 'MichOne', 'MICH');
    clientForProgramColumn($org->id, 'Active', 'IcoOne', 'ICO');
    clientForProgramColumn($org->id, 'Held', 'DhsHold', 'DHS Home Help', ['status' => 'On Hold']);

    $response = $this->actingAsWithTwoFactor($admin)->get(route('clients.index'));

    $response->assertOk()
        ->assertViewHas('stats', function ($stats) {
            expect($stats['active'])->toBe(4)
                ->and($stats['dhs'])->toBe(2)
                ->and($stats['mich'])->toBe(2);

            return true;
        })
        ->assertViewHas('rows', function ($rows) use ($response) {
            $stats = $response->viewData('stats');
            $activeRows = collect($rows)->filter(fn ($r) => ($r['status_key'] ?? '') === 'active');
            $dhs = $activeRows->where('program', 'DHS')->count();
            $mich = $activeRows->where('program', 'MICH')->count();

            expect($dhs)->toBe($stats['dhs'])
                ->and($mich)->toBe($stats['mich']);

            return true;
        })
        ->assertSee('2 active DHS · 2 active MICH', false);
});

test('client registry page embeds program_display in alpine rows payload', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    clientForProgramColumn($org->id, 'Payload', 'IcoClient', 'ICO');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Payload IcoClient', false)
        // @js() HTML-encodes JSON for Alpine (e.g. \u0022program_display\u0022:\u0022ICO\u0022).
        ->assertSee('program_display\u0022:\u0022ICO', false)
        ->assertSee('program\u0022:\u0022MICH', false);
});

test('authorizations list rows include program_display per client', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $dhsClient = clientForProgramColumn($org->id, 'Auth', 'DhsClient', 'DHS Home Help');
    $icoClient = clientForProgramColumn($org->id, 'Auth', 'IcoClient', 'ICO');

    foreach ([$dhsClient, $icoClient] as $client) {
        CareDetail::create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'start_date' => today()->subMonths(2),
            'end_date' => today()->addMonths(4),
            'status' => 'Active',
            'total_units' => 100,
        ]);
    }

    $this->actingAsWithTwoFactor($admin)
        ->get(route('authorizations'))
        ->assertOk()
        ->assertViewHas('rows', function ($rows) {
            $byName = collect($rows)->keyBy('name');

            expect($byName['Auth DhsClient']['program_display'])->toBe('DHS')
                ->and($byName['Auth IcoClient']['program_display'])->toBe('ICO');

            return true;
        })
        ->assertSee('program_display\u0022:\u0022ICO', false);
});

test('compliance tracker rows include program_display per client', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    clientForProgramColumn($org->id, 'Track', 'DaaaClient', 'DAAA');
    clientForProgramColumn($org->id, 'Track', 'BlankClient');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertViewHas('tracker', function ($tracker) {
            $byClient = collect($tracker)->keyBy('client');

            expect($byClient['Track DaaaClient']['program_display'])->toBe('DAAA')
                ->and($byClient['Track BlankClient']['program_display'])->toBe('—');

            return true;
        });
});

test('registry metrics service loads coverage type for program resolution', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->actingAs($admin);

    clientForProgramColumn($org->id, 'Metrics', 'DhsClient', 'DHS Home Help');

    $clients = app(RegistryMetricsService::class)->clients();
    $client = $clients->first(fn (Client $c) => trim($c->first_name.' '.$c->last_name) === 'Metrics DhsClient');

    expect($client)->not->toBeNull()
        ->and($client->relationLoaded('coverageType'))->toBeTrue()
        ->and($client->program_display)->toBe('DHS');
});

test('clients program audit command reports blank program clients', function () {
    $org = $this->createOrganization();
    clientForProgramColumn($org->id, 'Has', 'Program', 'MICH');
    clientForProgramColumn($org->id, 'Missing', 'Program');

    $this->artisan('clients:program-audit')
        ->assertSuccessful()
        ->expectsOutputToContain('Program renders "—"')
        ->expectsOutputToContain('Missing Program');
});
