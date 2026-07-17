<?php

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('two factor service verifies hashed otp', function () {
    $user = test()->createUser(User::ROLE_ADMIN);
    $user->update([
        'two_factor_code' => Hash::make('654321'),
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);

    $result = app(TwoFactorService::class)->verify($user, '654321');

    expect($result['success'])->toBeTrue();

    $user->refresh();
    expect($user->two_factor_code)->toBeNull();
});

test('two factor service rejects incorrect otp', function () {
    $user = test()->createUser(User::ROLE_ADMIN);
    $user->update([
        'two_factor_code' => Hash::make('654321'),
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);

    $result = app(TwoFactorService::class)->verify($user, '000000');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('attempt');
});
