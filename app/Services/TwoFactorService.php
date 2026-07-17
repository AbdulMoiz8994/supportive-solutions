<?php

namespace App\Services;

use App\Mail\OTPMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorService
{
    public function isLocked(): bool
    {
        $lockedUntil = session('2fa_locked_until');

        if (! $lockedUntil) {
            return false;
        }

        if (now()->timestamp >= $lockedUntil) {
            session()->forget(['2fa_locked_until', '2fa_attempts']);

            return false;
        }

        return true;
    }

    public function remainingLockoutSeconds(): int
    {
        $lockedUntil = session('2fa_locked_until');

        if (! $lockedUntil) {
            return 0;
        }

        return max(0, $lockedUntil - now()->timestamp);
    }

    public function canResend(User $user): bool
    {
        return ! $this->isResendCooldownActive($user)
            && ! $this->isHourlyResendLimitReached($user);
    }

    public function resendCooldownSeconds(User $user): int
    {
        $key = $this->resendCooldownKey($user);

        if (! RateLimiter::tooManyAttempts($key, 1)) {
            return 0;
        }

        return RateLimiter::availableIn($key);
    }

    public function generateAndSend(User $user, string $method): RedirectResponse
    {
        if ($this->isLocked()) {
            return back()->with(
                'error',
                'Too many failed attempts. Please try again in '.$this->remainingLockoutSeconds().' seconds.'
            );
        }

        if (! $this->canResend($user)) {
            if ($this->isResendCooldownActive($user)) {
                return back()->with(
                    'error',
                    'Please wait '.$this->resendCooldownSeconds($user).' seconds before requesting a new code.'
                );
            }

            return back()->with('error', 'You have reached the hourly limit for verification code requests.');
        }

        $otp = (string) random_int(100000, 999999);
        $expiryMinutes = config('two_factor.otp_expiry_minutes', 10);

        $user->two_factor_code = Hash::make($otp);
        $user->two_factor_expires_at = now()->addMinutes($expiryMinutes);
        $user->save();

        session([
            '2fa_method' => $method,
            '2fa_attempts' => 0,
        ]);

        $this->recordResend($user);

        if ($method === 'email') {
            Mail::to($user->email)->send(new OTPMail($otp));
        } else {
            Log::info("SMS OTP sent for user {$user->id}");
        }

        $redirect = redirect()
            ->route('two-factor.verify')
            ->with('success', 'A new verification code has been sent.');

        if (config('app.debug')) {
            $redirect->with('debug_otp', $otp);
        }

        return $redirect;
    }

    public function verify(User $user, string $otp): array
    {
        if ($this->isLocked()) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please try again in '.$this->remainingLockoutSeconds().' seconds.',
            ];
        }

        if (! $user->two_factor_code || ! $user->two_factor_expires_at || $user->two_factor_expires_at->isPast()) {
            return [
                'success' => false,
                'message' => 'Your verification code has expired. Please request a new one.',
            ];
        }

        if (! Hash::check($otp, $user->two_factor_code)) {
            $attempts = (int) session('2fa_attempts', 0) + 1;
            session(['2fa_attempts' => $attempts]);

            $maxAttempts = config('two_factor.max_attempts', 5);

            if ($attempts >= $maxAttempts) {
                $lockoutMinutes = config('two_factor.lockout_minutes', 15);
                session(['2fa_locked_until' => now()->addMinutes($lockoutMinutes)->timestamp]);

                $user->two_factor_code = null;
                $user->two_factor_expires_at = null;
                $user->save();

                return [
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new code after '.$lockoutMinutes.' minutes.',
                ];
            }

            $remaining = $maxAttempts - $attempts;

            return [
                'success' => false,
                'message' => "Invalid verification code. {$remaining} attempt(s) remaining.",
            ];
        }

        $user->two_factor_verified_at = now();
        $user->two_factor_code = null;
        $user->two_factor_expires_at = null;
        $user->save();

        session([
            '2fa_verified' => true,
        ]);
        session()->forget(['2fa_attempts', '2fa_locked_until']);

        return [
            'success' => true,
            'message' => null,
        ];
    }

    protected function resendCooldownKey(User $user): string
    {
        return 'two-factor-resend:'.$user->id;
    }

    protected function resendHourlyKey(User $user): string
    {
        return 'two-factor-resend-hourly:'.$user->id;
    }

    protected function isResendCooldownActive(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->resendCooldownKey($user), 1);
    }

    protected function isHourlyResendLimitReached(User $user): bool
    {
        $maxPerHour = config('two_factor.resend_max_per_hour', 5);

        return RateLimiter::tooManyAttempts($this->resendHourlyKey($user), $maxPerHour);
    }

    protected function recordResend(User $user): void
    {
        $cooldown = config('two_factor.resend_cooldown_seconds', 60);

        RateLimiter::hit($this->resendCooldownKey($user), $cooldown);
        RateLimiter::hit($this->resendHourlyKey($user), 3600);
    }
}
