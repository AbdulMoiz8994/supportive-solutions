<?php

namespace App\Http\Middleware;

use App\Services\GlobalSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function __construct(
        protected GlobalSettingsService $settingsService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && ! session('2fa_verified') && $this->requiresTwoFactor($user)) {
            $exemptRoutes = [
                'two-factor.choice',
                'two-factor.send',
                'two-factor.resend',
                'two-factor.verify',
                'two-factor.verify.post',
                'logout',
            ];

            if (! $request->routeIs($exemptRoutes)) {
                return redirect()->route('two-factor.choice');
            }
        }

        return $next($request);
    }

    protected function requiresTwoFactor($user): bool
    {
        // Master switch (TWO_FACTOR_ENFORCED=false) — disables 2FA entirely for
        // simple email+password login during local/dev testing. Default: on.
        if (! config('two_factor.enforced', true)) {
            return false;
        }

        // Narrow, testing-only exemption: named throwaway accounts skip 2FA even
        // while everyone else (including super admins) still needs it.
        $email = strtolower((string) $user->email);
        if ($email !== '' && in_array($email, (array) config('two_factor.exempt_emails', []), true)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        try {
            return $this->settingsService->isTwoFactorRequired();
        } catch (\Throwable) {
            return true;
        }
    }
}
