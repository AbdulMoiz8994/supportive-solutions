<?php

use App\Services\Payroll\AccountantsWorldErrorFormatter;

test('error formatter returns verify specific message for not found api response', function () {
    $message = app(AccountantsWorldErrorFormatter::class)->formatFromApiResult([
        'raw' => [
            'message' => 'Not found',
            'http_status' => 404,
        ],
    ], AccountantsWorldErrorFormatter::CONTEXT_VERIFY);

    expect($message)->toContain('404')
        ->and($message)->toContain('could not find this employee');
});

test('error formatter returns human friendly verify fallback when body is html', function () {
    $message = app(AccountantsWorldErrorFormatter::class)->formatFromApiResult([
        'raw' => [
            'http_status' => 404,
            'body_text' => '<html><head><title>404 Not Found</title></head><body>Page missing</body></html>',
        ],
    ], AccountantsWorldErrorFormatter::CONTEXT_VERIFY);

    expect($message)->toBe('AccountantsWorld could not find this employee (HTTP 404). Confirm they exist in AW, or use the correct AW employee ID.');
});

test('error formatter returns legacy stored guidance', function () {
    $employee = new \App\Models\Employee([
        'aw_setup_error_context' => AccountantsWorldErrorFormatter::CONTEXT_LEGACY,
    ]);

    $message = app(AccountantsWorldErrorFormatter::class)->formatStored($employee);

    expect($message)->toContain('before detailed error tracking');
});
