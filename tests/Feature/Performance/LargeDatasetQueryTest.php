<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => seedModuleBasics());

test('client index handles large dataset without excessive queries', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    for ($i = 0; $i < 100; $i++) {
        $this->createClient($org->id, [
            'first_name' => "Bulk{$i}",
            'last_name' => 'Client',
        ]);
    }

    DB::enableQueryLog();
    $start = microtime(true);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'));

    $elapsed = microtime(true) - $start;
    $queryCount = count(DB::getQueryLog());

    $response->assertOk();
    expect($queryCount)->toBeLessThan(200)
        ->and($elapsed)->toBeLessThan(10.0);
});

test('schedule index handles many shifts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);

    for ($i = 0; $i < 50; $i++) {
        $this->createSchedule($org->id, $client->id, $employee->id, [
            'date' => today()->addDays($i % 30)->toDateString(),
        ]);
    }

    DB::enableQueryLog();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.index'))
        ->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(100);
});

test('payroll index handles many pay records', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);

    for ($i = 0; $i < 50; $i++) {
        payrollTestRecord($org->id, $employee->id, $client->id, [
            'period_key' => '2026-'.str_pad((string) ($i % 12 + 1), 2, '0', STR_PAD_LEFT),
        ]);
    }

    DB::enableQueryLog();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll'))
        ->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(100);
});
