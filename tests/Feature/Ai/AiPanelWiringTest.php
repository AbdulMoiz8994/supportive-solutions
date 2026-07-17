<?php

use App\Models\Location;
use App\Models\User;
use Database\Seeders\LookupTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(LookupTableSeeder::class);
});

test('client show wires the Daily-brief AI panel and document recognition', function () {
    $org = $this->createOrganization();
    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Jane', 'last_name' => 'Smith']);

    $html = $this->actingAsWithTwoFactor($user)
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->getContent();

    // AI case-summary panel wired to the client-summary endpoint
    expect($html)->toContain('Daily brief')
        ->toContain('aiSummaryPanel(')
        ->toContain(route('ai.client-summary', $client->id, false));

    // Document recognition + real save wired on the Documents tab
    expect($html)->toContain('docScan(')
        ->toContain(route('ai.recognize-document', [], false))
        ->toContain(route('documents.store', [], false));
});

test('caregiver show wires the AI-summary panel and button', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $user = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'location_id' => $location->id,
    ]);

    $html = $this->actingAsWithTwoFactor($user)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('caregivers.show', $caregiver->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('AI summary')
        ->toContain('aiSummaryPanel(')
        ->toContain(route('ai.caregiver-summary', $caregiver->id, false));
});
