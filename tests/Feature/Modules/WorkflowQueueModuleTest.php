<?php

use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access workflow queues', function () {
    $this->get(route('workflow-queues'))->assertRedirect(route('signin'));
});

test('admin can view workflow queues with demo cards', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('workflow-queues'))
        ->assertOk()
        ->assertSee('Owner Approval Queue')
        ->assertSee('Exceptions')
        ->assertSee('Verify photo ID & fax DHS packet');
});

test('completing human task removes it from queue', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('workflow-queues.action', 'demo-task-khalil-id'), ['queue_action' => 'complete'])
        ->assertRedirect(route('workflow-queues'))
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'Verify photo ID & fax DHS packet'));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('workflow-queues'))
        ->assertOk()
        ->assertSee('Marked complete:')
        ->assertDontSee('data-queue-slug="demo-task-khalil-id"');
});

test('live billing approval removes item from queue', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Queue', 'last_name' => 'Client']);

    $billing = \App\Models\Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-WQ-001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 250,
        'status' => 'Pending',
    ]);

    config(['workflow_queues.demo_fallback' => false]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('workflow-queues'))
        ->assertOk()
        ->assertSee('INV-WQ-001');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('workflow-queues.action', 'billing-'.$billing->id), [
            'queue_action' => 'approve',
            'approve_type' => 'billing',
            'approve_id' => $billing->id,
        ])
        ->assertRedirect(route('workflow-queues'));

    $this->assertDatabaseHas('billings', ['id' => $billing->id, 'status' => 'Sent']);
});

test('workflow queue approve returns refreshed kpi counts via json', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Queue', 'last_name' => 'Client']);

    collect(range(1, 3))->each(function (int $index) use ($org, $client) {
        \App\Models\Billing::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-WQ-JSON-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_amount' => 100 + $index,
            'status' => 'Pending',
        ]);
    });

    $billing = \App\Models\Billing::where('invoice_number', 'INV-WQ-JSON-001')->firstOrFail();

    config(['workflow_queues.demo_fallback' => false]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('workflow-queues.action', 'billing-'.$billing->id), [
            'queue_action' => 'approve',
            'approve_type' => 'billing',
            'approve_id' => $billing->id,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'INV-WQ-JSON-001'))
        ->assertJsonPath('approvalCount', 2)
        ->assertJsonPath('kpis.0.value', '2')
        ->assertJsonPath('removedSlug', 'billing-'.$billing->id);
});

test('completing human task via json decrements human task kpi', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('workflow-queues.action', 'demo-task-khalil-id'), ['queue_action' => 'complete'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'Verify photo ID & fax DHS packet'))
        ->assertJson(fn ($json) => $json->where('humanTaskCount', fn ($count) => is_int($count))->etc());
});

test('workflow queues paginates approval cards and load more returns the next page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Paged', 'last_name' => 'Client']);

    config([
        'workflow_queues.demo_fallback' => false,
        'workflow_queues.approvals_per_page' => 5,
    ]);

    collect(range(1, 12))->each(function (int $index) use ($org, $client) {
        \App\Models\Billing::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-WQ-PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_amount' => 100 + $index,
            'status' => 'Pending',
        ]);
    });

    $page = $this->actingAsWithTwoFactor($admin)
        ->get(route('workflow-queues'))
        ->assertOk();

    $html = $page->getContent();
    preg_match_all('/<article[^>]*data-queue-slug="(billing-\d+)"/', $html, $firstPageMatches);
    $firstPageSlugs = collect($firstPageMatches[1] ?? [])->unique()->values();

    expect($firstPageSlugs)->toHaveCount(5);

    $more = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('workflow-queues.approvals', ['offset' => 5, 'limit' => 5]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('approvalsMeta.total', 12)
        ->assertJsonPath('approvalsMeta.offset', 5)
        ->assertJsonPath('approvalsMeta.loaded', 10)
        ->assertJsonPath('approvalsMeta.hasMore', true);

    preg_match_all('/<article[^>]*data-queue-slug="(billing-\d+)"/', $more->json('html'), $secondPageMatches);
    $secondPageSlugs = collect($secondPageMatches[1] ?? [])->unique()->values();

    expect($secondPageSlugs)->toHaveCount(5)
        ->and($firstPageSlugs->intersect($secondPageSlugs))->toBeEmpty();
});

test('workflow queues can filter and sort approval cards', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Filter', 'last_name' => 'Client']);

    config(['workflow_queues.demo_fallback' => false]);

    \App\Models\Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-WQ-FILTER-001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 150,
        'status' => 'Pending',
    ]);

    $employee = $this->createEmployee($org->id, [
        'first_name' => 'Filter',
        'last_name' => 'Caregiver',
        'has_background_check' => 0,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('workflow-queues', ['filter' => 'billing']))
        ->assertOk()
        ->assertSee('INV-WQ-FILTER-001')
        ->assertDontSee('OIG flag — Filter Caregiver');

    $filtered = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('workflow-queues.approvals', ['filter' => 'background', 'offset' => 0]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('approvalsMeta.total', 1)
        ->assertJsonPath('filter', 'background');

    expect($filtered->json('html'))
        ->toContain('background-'.$employee->id)
        ->not->toContain('INV-WQ-FILTER-001');

    $sorted = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('workflow-queues.approvals', ['sort' => 'title', 'offset' => 0]))
        ->assertOk()
        ->assertJsonPath('sort', 'title');

    expect($sorted->json('html'))->toContain('INV-WQ-FILTER-001');

    $slaFirst = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('workflow-queues.approvals', ['sort' => 'sla', 'offset' => 0]))
        ->assertOk()
        ->json('html');

    $slaLast = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('workflow-queues.approvals', ['sort' => 'sla_desc', 'offset' => 0]))
        ->assertOk()
        ->assertJsonPath('sort', 'sla_desc')
        ->json('html');

    expect($slaFirst)->not->toBe($slaLast);
});
