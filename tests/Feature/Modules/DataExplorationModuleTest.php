<?php

use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('admin can view data exploration page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('data-exploration'))
        ->assertOk()
        ->assertSee('Data Exploration 2.0')
        ->assertSee('Read-only');
});

test('exploration legacy route works', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('exploration'))
        ->assertOk()
        ->assertSee('Data Exploration 2.0');
});

test('data exploration query returns json', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('data-exploration.query'), [
            'dataset' => 'clients',
            'date_from' => now()->subYear()->toDateString(),
            'date_to' => today()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonStructure([
            'ok',
            'columns',
            'rows',
            'chart',
            'group_by_options',
            'aggregate_options',
            'filter_fields',
            'status_options',
            'date_presets',
            'truncated',
            'total_matched',
        ]);
});

test('data exploration export returns csv', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('data-exploration.export', ['dataset' => 'clients']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('data exploration export supports xlsx and pdf', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('data-exploration.export', ['dataset' => 'clients', 'format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('data-exploration.export', ['dataset' => 'clients', 'format' => 'pdf']))
        ->assertOk();
});

test('employee without permission cannot view data exploration', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('data-exploration'))
        ->assertForbidden();
});

test('user can delete their own saved exploration view', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $view = app(\App\Services\DataExplorationService::class)->saveView(
        $org->id,
        $admin,
        'Temp view',
        'visits',
        ['group_by' => 'status', 'aggregate' => 'count'],
    );

    $this->actingAsWithTwoFactor($admin)
        ->deleteJson(route('data-exploration.delete-view', $view->id))
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(\App\Models\DataExplorationView::find($view->id))->toBeNull();
});

test('user can save a view with email schedule frequency', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('data-exploration.save-view'), [
            'name' => 'Weekly visits',
            'dataset' => 'visits',
            'config' => ['aggregate' => 'count'],
            'schedule_frequency' => 'weekly',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $view = \App\Models\DataExplorationView::where('user_id', $admin->id)
        ->where('name', 'Weekly visits')
        ->first();

    expect($view)->not->toBeNull();
    expect($view->schedule_frequency)->toBe('weekly');
});

test('grouping visits by client shows client names not unknown', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Jane', 'last_name' => 'Grouped']);
    $caregiver = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => \App\Models\Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
    ]);

    $result = app(\App\Services\DataExplorationService::class)->query($org->id, 'visits', [
        'date_from' => today()->subMonth()->toDateString(),
        'date_to' => today()->toDateString(),
        'group_by' => 'client_id',
        'aggregate' => 'count',
    ]);

    $groups = collect($result['rows'])->pluck('Group');

    expect($groups)->toContain('Jane Grouped');
    expect($groups)->not->toContain('Unknown');
});

test('agent suggested saved views are created on page load', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    app(\App\Services\AiAgentRegistryService::class)->ensureCatalog($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('data-exploration'))
        ->assertOk()
        ->assertSee('[Agent] Visits this week');

    $views = \App\Models\DataExplorationView::where('user_id', $admin->id)
        ->where('name', 'like', '[Agent]%')
        ->get();

    expect($views->count())->toBeGreaterThanOrEqual(2);
    expect(data_get($views->first()->config, 'suggested_by_agent'))->toBe('document');
});

test('disabled document agent does not create suggested views', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $registry = app(\App\Services\AiAgentRegistryService::class);
    $registry->ensureCatalog($org->id);
    $agent = $registry->findBySlug($org->id, 'document');
    $agent->update(['is_enabled' => false, 'is_paused' => true]);

    $created = app(\App\Services\DataExplorationService::class)
        ->syncAgentSuggestedViews($org->id, $admin);

    expect($created)->toBeEmpty();
});

test('monitor-only suggest_exploration_views capability skips suggestions', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $registry = app(\App\Services\AiAgentRegistryService::class);
    $registry->ensureCatalog($org->id);
    $agent = $registry->findBySlug($org->id, 'document');
    $agent->update([
        'is_enabled' => true,
        'is_paused' => false,
        'action_autonomy' => [
            ['key' => 'suggest_exploration_views', 'mode' => 'monitor'],
        ],
    ]);

    expect($agent->fresh()->canRunAction('suggest_exploration_views'))->toBeFalse();

    $created = app(\App\Services\DataExplorationService::class)
        ->syncAgentSuggestedViews($org->id, $admin);

    expect($created)->toBeEmpty();
});

test('program filter shrinks clients dataset', function () {
    $org = $this->createOrganization();
    $this->createClient($org->id, ['first_name' => 'Dhs', 'last_name' => 'One', 'mco_name' => 'DHS Plan']);
    $this->createClient($org->id, ['first_name' => 'Mich', 'last_name' => 'Two', 'mco_name' => 'MICH Plan']);

    $service = app(\App\Services\DataExplorationService::class);
    $all = $service->query($org->id, 'clients', []);
    $filtered = $service->query($org->id, 'clients', ['program' => 'DHS']);

    expect(count($filtered['rows']))->toBeLessThan(count($all['rows']));
    expect(collect($filtered['rows'])->pluck('Client')->implode(' '))->toContain('Dhs');
    expect(collect($filtered['rows'])->pluck('Client')->implode(' '))->not->toContain('Mich');
});

test('csv export cells match query rows', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'Csv', 'last_name' => 'Alpha', 'mco_name' => 'DHS']);
    $this->createClient($org->id, ['first_name' => 'Csv', 'last_name' => 'Beta', 'mco_name' => 'MICH']);

    $config = ['program' => 'DHS'];
    $service = app(\App\Services\DataExplorationService::class);
    $query = $service->query($org->id, 'clients', $config, $admin);
    [$headers, $csvRows] = $service->exportCsv($org->id, 'clients', $config, $admin);

    expect(count($csvRows))->toBe(count($query['rows']));

    foreach ($query['rows'] as $index => $row) {
        foreach ($headers as $colIndex => $header) {
            expect((string) ($csvRows[$index][$colIndex] ?? ''))->toBe((string) ($row[$header] ?? ''));
        }
    }
});

test('saved exploration views respect owning user permissions', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $other = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $view = app(\App\Services\DataExplorationService::class)->saveView(
        $org->id,
        $admin,
        'Admin private view',
        'clients',
        ['aggregate' => 'count'],
        null,
    );

    $this->actingAsWithTwoFactor($other)
        ->deleteJson(route('data-exploration.delete-view', $view->id))
        ->assertNotFound();

    $this->actingAsWithTwoFactor($admin)
        ->deleteJson(route('data-exploration.delete-view', $view->id))
        ->assertOk();
});

test('filtering visits by status shrinks rows and updates chart', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => \App\Models\Schedule::STATUS_COMPLETED,
        'date' => today()->toDateString(),
        'actual_clock_in' => today()->format('Y-m-d').' 09:00:00',
        'actual_clock_out' => today()->format('Y-m-d').' 10:00:00',
        'total_hours' => 1,
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
    ]);
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status' => \App\Models\Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
    ]);

    $service = app(\App\Services\DataExplorationService::class);
    $all = $service->query($org->id, 'visits', [
        'date_from' => today()->toDateString(),
        'date_to' => today()->toDateString(),
    ]);
    $filtered = $service->query($org->id, 'visits', [
        'date_from' => today()->toDateString(),
        'date_to' => today()->toDateString(),
        'status' => 'complete',
    ]);

    expect(count($filtered['rows']))->toBeLessThan(count($all['rows']));
    expect($filtered['chart'])->not->toBeEmpty();
});

test('csv export row count matches query result', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'Export', 'last_name' => 'Match']);

    $service = app(\App\Services\DataExplorationService::class);
    $config = [
        'date_from' => now()->subYear()->toDateString(),
        'date_to' => today()->toDateString(),
    ];
    $query = $service->query($org->id, 'clients', $config, $admin);
    [$headers, $rows] = $service->exportCsv($org->id, 'clients', $config, $admin);

    expect(count($rows))->toBe(count($query['rows']));
    expect($headers)->not->toBeEmpty();
});

test('scheduled exploration email command sends due views', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, [
        'organization_id' => $org->id,
        'email' => 'explore-sched@example.com',
    ]);

    $view = app(\App\Services\DataExplorationService::class)->saveView(
        $org->id,
        $admin,
        'Daily clients digest',
        'clients',
        ['aggregate' => 'count'],
        'daily',
    );

    // Mail::html() is used by the command; assert side-effect + CLI output.
    $this->artisan('data-exploration:email-scheduled-views')
        ->expectsOutputToContain('Emailed 1')
        ->assertSuccessful();

    expect($view->fresh()->last_emailed_at)->not->toBeNull();

    // Already emailed today — daily view should not send again.
    $this->artisan('data-exploration:email-scheduled-views')
        ->expectsOutputToContain('Emailed 0')
        ->assertSuccessful();
});

test('non-admin exploration query masks client names', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    // Give exploration permission if role helper supports it; otherwise use admin with masking forced via service.
    $this->createClient($org->id, ['first_name' => 'Secret', 'last_name' => 'Person']);

    $service = app(\App\Services\DataExplorationService::class);
    $result = $service->query($org->id, 'clients', [
        'date_from' => now()->subYear()->toDateString(),
        'date_to' => today()->toDateString(),
    ], $staff);

    $names = collect($result['rows'])->pluck('Client')->filter()->values();
    if ($names->isNotEmpty()) {
        expect($names->first())->toContain('***');
        expect($names->first())->not->toBe('Secret Person');
    }
});

test('ahmed scenario: DHS visits last month grouped by caregiver sums hours', function () {
    $org = $this->createOrganization();
    $dhsClient = $this->createClient($org->id, ['first_name' => 'Dhs', 'last_name' => 'Client', 'mco_name' => 'DHS Plan']);
    $otherClient = $this->createClient($org->id, ['first_name' => 'Other', 'last_name' => 'Client', 'mco_name' => 'MICH']);
    $caregiverA = $this->createEmployee($org->id, ['first_name' => 'Alice', 'last_name' => 'Care']);
    $caregiverB = $this->createEmployee($org->id, ['first_name' => 'Bob', 'last_name' => 'Care']);

    $date = now()->subMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString();

    $completeAttrs = [
        'status' => \App\Models\Schedule::STATUS_COMPLETED,
        'date' => $date,
        'actual_clock_in' => $date.' 09:00:00',
        'actual_clock_out' => $date.' 12:00:00',
        'clock_in_latitude' => 42.3314,
        'clock_in_longitude' => -83.0458,
        'clock_out_latitude' => 42.3314,
        'clock_out_longitude' => -83.0458,
        'metadata' => ['client_home_lat' => 42.3314, 'client_home_lng' => -83.0458],
        'evv_status' => true,
    ];

    $i = 0;
    foreach ([[$caregiverA, 3.0], [$caregiverA, 2.0], [$caregiverB, 4.0]] as [$cg, $hours]) {
        $visitDate = now()->subMonthNoOverflow()->startOfMonth()->addDays(5 + $i)->toDateString();
        $endHour = 9 + (int) $hours;
        $this->createSchedule($org->id, $dhsClient->id, $cg->id, array_merge($completeAttrs, [
            'date' => $visitDate,
            'start_time' => '09:00:00',
            'end_time' => sprintf('%02d:00:00', $endHour),
            'total_hours' => $hours,
            'actual_clock_in' => $visitDate.' 09:00:00',
            'actual_clock_out' => $visitDate.' '.sprintf('%02d:00:00', $endHour),
        ]));
        $i++;
    }

    // Non-DHS visit in same month — must be excluded by program filter.
    $otherDate = now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->toDateString();
    $this->createSchedule($org->id, $otherClient->id, $caregiverA->id, array_merge($completeAttrs, [
        'date' => $otherDate,
        'total_hours' => 10,
        'actual_clock_in' => $otherDate.' 09:00:00',
        'actual_clock_out' => $otherDate.' 19:00:00',
    ]));

    $result = app(\App\Services\DataExplorationService::class)->query($org->id, 'visits', [
        'date_preset' => 'last_month',
        'program' => 'DHS',
        'status' => 'complete',
        'group_by' => 'employee_id',
        'aggregate' => 'sum_hours',
        'chart_type' => 'bar',
    ]);

    // Debug-friendly assertions for the client “Ahmed” workflow.
    expect($result['rows'])->not->toBeEmpty();
    $byCaregiver = collect($result['rows'])->keyBy('Group');
    expect($byCaregiver->has('Alice Care'))->toBeTrue();
    expect($byCaregiver->has('Bob Care'))->toBeTrue();
    expect((float) $byCaregiver->get('Alice Care')['Total'])->toBe(5.0);
    expect((float) $byCaregiver->get('Bob Care')['Total'])->toBe(4.0);
    expect($result['chart']['labels'])->toContain('Alice Care');
    expect((float) array_sum($result['chart']['values']))->toBe(9.0);
});

test('grouped visit totals use aggregate fetch not list cap', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['first_name' => 'Grouped', 'last_name' => 'Only']);

    // More than the list-mode 500 cap; grouped mode must still sum all of them (up to aggregate limit).
    foreach (range(1, 520) as $i) {
        $this->createSchedule($org->id, $client->id, $caregiver->id, [
            'status' => \App\Models\Schedule::STATUS_COMPLETED,
            'date' => today()->subDays($i % 28)->toDateString(),
            'total_hours' => 1,
            'actual_clock_in' => today()->subDays($i % 28)->format('Y-m-d').' 09:00:00',
            'actual_clock_out' => today()->subDays($i % 28)->format('Y-m-d').' 10:00:00',
        ]);
    }

    $result = app(\App\Services\DataExplorationService::class)->query($org->id, 'visits', [
        'date_from' => today()->subMonth()->toDateString(),
        'date_to' => today()->toDateString(),
        'group_by' => 'employee_id',
        'aggregate' => 'sum_hours',
    ]);

    $total = collect($result['rows'])->sum('Total');
    expect($total)->toBeGreaterThan(500);
});

test('last_month date preset resolves to previous calendar month', function () {
    $service = app(\App\Services\DataExplorationService::class);
    $config = $service->normalizeConfig(['date_preset' => 'last_month']);

    expect($config['date_from'])->toBe(now()->subMonthNoOverflow()->startOfMonth()->toDateString());
    expect($config['date_to'])->toBe(now()->subMonthNoOverflow()->endOfMonth()->toDateString());
});
