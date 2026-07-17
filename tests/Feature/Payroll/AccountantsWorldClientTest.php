<?php

use App\Services\Payroll\AccountantsWorldClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('accountants world client uses oauth bearer token when client secret is configured', function () {
    Cache::flush();

    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_auth_mode' => 'oauth',
        'payroll.accountants_world_oauth_client_id' => 'aw-client-id',
        'payroll.accountants_world_oauth_client_secret' => 'aw-client-secret',
        'payroll.accountants_world_oauth_token_url' => 'https://auth.example.com/connect/token',
    ]);

    Http::fake([
        'https://auth.example.com/connect/token' => Http::response([
            'access_token' => 'test-bearer-token',
            'expires_in' => 3600,
        ], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/PaySchedules' => Http::response([], 200),
    ]);

    app(AccountantsWorldClient::class)->getPaySchedules();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://auth.example.com/connect/token'
            && ($request->data()['client_id'] ?? null) === 'aw-client-id'
            && ($request->data()['client_secret'] ?? null) === 'aw-client-secret'
            && ($request->data()['scope'] ?? null) === 'payroll_api';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/payroll/PaySchedules')
            && ($request->header('Authorization')[0] ?? '') === 'Bearer test-bearer-token';
    });
});

test('accountants world client create employee sends import payload to employee api', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
        'payroll.accountants_world_api_key' => 'aw-test-key',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/import' => Http::response([
            'success' => true,
            'numberImported' => 1,
            'numberUpdated' => 0,
            'numberFailed' => 0,
            'employeesModified' => [['employeeId' => 42]],
        ], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->createEmployee([
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'ssn' => '123456789',
        'payRate' => 14.5,
        'payType' => 'hourly',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['employee_id'])->toBe('42');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), '/client/employee/import')
            && ($body[0]['firstName'] ?? null) === 'Jane'
            && ($body[0]['ssn'] ?? null) === '123456789'
            && ($body[0]['payTypes'][0]['rate'] ?? null) === 14.5;
    });
});

test('accountants world client uses app id as api key when api key config is missing', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
        'payroll.accountants_world_api_key' => null,
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/import' => Http::response(['employeeId' => 'AW-EMP-99'], 201),
    ]);

    app(AccountantsWorldClient::class)->createEmployee(['firstName' => 'Jane', 'lastName' => 'Doe']);

    Http::assertSent(fn ($request) => $request->header('X-API-Key')[0] === 'c9c999aa4fc04b14a4f371aad354424e');
});

test('accountants world client get pay schedules calls payroll api', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/PaySchedules' => Http::response([
            ['payScheduleId' => 12, 'scheduleName' => 'Biweekly', 'forContractors' => false],
        ], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->getPaySchedules();

    expect($result['success'])->toBeTrue()
        ->and($result['data'][0]['payScheduleId'])->toBe(12);
});

test('accountants world client get next payroll data uses pay schedule id', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/GetNextPayrollData/12' => Http::response([
            'keyData' => ['payrollId' => 501],
            'timeData' => [['empId' => 7, 'payTypes' => []]],
        ], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->getNextPayrollData(12);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['keyData']['payrollId'])->toBe(501);
});

test('accountants world client update payroll data posts to payroll api', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/UpdatePayrollData' => Http::response([
            'success' => true,
            'messages' => ['Payroll updated'],
        ], 200),
    ]);

    $payload = [
        'keyData' => ['payrollId' => 501],
        'timeData' => [['empId' => 7, 'payTypes' => [['payTypeCode' => 'REG', 'hours' => 40, 'amount' => 600]]]],
    ];

    $result = app(AccountantsWorldClient::class)->updatePayrollData($payload);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['messages'][0])->toBe('Payroll updated');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/payroll/UpdatePayrollData'));
});

test('accountants world client get payroll pay stubs uses payroll id', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/PayrollPayStubs/501' => Http::response([
            ['paycheckID' => 9001, 'empID' => 7, 'grossPay' => 600, 'netPay' => 500],
        ], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->getPayrollPayStubs(501);

    expect($result['success'])->toBeTrue()
        ->and($result['data'][0]['paycheckID'])->toBe(9001);
});

test('accountants world client get payroll details uses date range path', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/PayrollDetails/*' => Http::response([
            'payrollId' => 501,
            'scheduleName' => 'Biweekly',
        ], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->getPayrollDetails('2026-05-01T00:00:00', '2026-05-31T23:59:59');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['payrollId'])->toBe(501);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/payroll/PayrollDetails/'));
});

test('accountants world client get employee verifies by id', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/info/AW-EMP-7*' => Http::response(['name' => 'Jane Doe'], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->getEmployee('AW-EMP-7');

    expect($result['success'])->toBeTrue()
        ->and($result['employee_id'])->toBe('AW-EMP-7');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/client/employee/info/AW-EMP-7'));
});

test('accountants world client lookup employee by ssn searches employee list', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/list' => Http::response([
            'success' => true,
            'employeeList' => [['employeeId' => 8, 'ssn' => '123-45-6789']],
        ], 200),
    ]);

    $result = app(AccountantsWorldClient::class)->lookupEmployeeBySsn('123456789');

    expect($result['success'])->toBeTrue()
        ->and($result['employee_id'])->toBe('8');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/client/employee/list'));
});

test('accountants world client api key mode ignores oauth secret in config', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_auth_mode' => 'api_key',
        'payroll.accountants_world_app_id' => 'employer-api-key',
        'payroll.accountants_world_oauth_client_secret' => 'should-not-be-used',
    ]);

    $client = app(AccountantsWorldClient::class);

    expect($client->authMode())->toBe(AccountantsWorldClient::AUTH_MODE_API_KEY)
        ->and($client->usesOAuthAuth())->toBeFalse()
        ->and($client->usesApiKeyAuth())->toBeTrue()
        ->and($client->validateAuthConfiguration()['valid'])->toBeTrue();
});

test('accountants world test connection hints when api key alone returns 401', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_auth_mode' => 'api_key',
        'payroll.accountants_world_app_id' => 'employer-api-key',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/PaySchedules' => Http::response('', 401),
    ]);

    $result = app(AccountantsWorldClient::class)->testConnection();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('x-api-key');
});
