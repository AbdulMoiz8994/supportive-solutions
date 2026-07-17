<?php

use App\Mail\OTPMail;
use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    Cache::flush();
    config([
        'two_factor.otp_expiry_minutes' => 10,
        'two_factor.max_attempts' => 5,
        'two_factor.lockout_minutes' => 15,
        'two_factor.resend_cooldown_seconds' => 60,
        'two_factor.resend_max_per_hour' => 5,
    ]);
});

test('otp is generated randomly and stored hashed', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAs($user)
        ->post(route('two-factor.send'), ['method' => 'email'])
        ->assertRedirect(route('two-factor.verify'));

    $user->refresh();

    expect($user->two_factor_code)->not->toBe('123456')
        ->and($user->two_factor_code)->not->toBeNull()
        ->and(Hash::needsRehash($user->two_factor_code))->toBeFalse()
        ->and($user->two_factor_expires_at)->not->toBeNull()
        ->and($user->two_factor_expires_at->isFuture())->toBeTrue();

    Mail::assertSent(OTPMail::class, function (OTPMail $mail) use ($user) {
        return Hash::check($mail->otp, $user->two_factor_code)
            && strlen($mail->otp) === 6;
    });
});

test('valid otp verifies user and clears code', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $otp = '482910';

    $user->update([
        'two_factor_code' => Hash::make($otp),
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->post(route('two-factor.verify.post'), ['otp' => $otp])
        ->assertRedirect('/dashboard');

    $user->refresh();

    expect(session('2fa_verified'))->toBeTrue()
        ->and($user->two_factor_code)->toBeNull()
        ->and($user->two_factor_expires_at)->toBeNull()
        ->and($user->two_factor_verified_at)->not->toBeNull();
});

test('expired otp is rejected', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $user->update([
        'two_factor_code' => Hash::make('111111'),
        'two_factor_expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->post(route('two-factor.verify.post'), ['otp' => '111111'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(session('2fa_verified'))->toBeNull();
});

test('invalid otp increments attempts and locks after max attempts', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $service = app(TwoFactorService::class);

    $user->update([
        'two_factor_code' => Hash::make('999999'),
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->post(route('two-factor.verify.post'), ['otp' => '000000'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    $user->refresh();

    expect($user->two_factor_code)->toBeNull()
        ->and($service->isLocked())->toBeTrue();
});

test('resend is throttled by cooldown', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    RateLimiter::clear('two-factor-resend:'.$user->id);
    RateLimiter::clear('two-factor-resend-hourly:'.$user->id);

    $this->actingAs($user)
        ->post(route('two-factor.send'), ['method' => 'email'])
        ->assertRedirect(route('two-factor.verify'));

    $this->actingAs($user)
        ->post(route('two-factor.resend'))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('verify page redirects when no pending otp', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAs($user)
        ->get(route('two-factor.verify'))
        ->assertRedirect(route('two-factor.choice'))
        ->assertSessionHas('error');
});
