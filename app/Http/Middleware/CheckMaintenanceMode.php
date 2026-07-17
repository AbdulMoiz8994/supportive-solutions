<?php

namespace App\Http\Middleware;

use App\Services\GlobalSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function __construct(
        protected GlobalSettingsService $settingsService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        try {
            if (! $this->settingsService->isMaintenanceModeEnabled()) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(503, 'The application is currently in maintenance mode.');
        }

        return redirect()->route('maintenance');
    }

    protected function shouldBypass(Request $request): bool
    {
        if ($request->routeIs(
            'signin',
            'signin.store',
            'logout',
            'maintenance',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'two-factor.*',
            'setup-account',
            'setup-account.store'
        )) {
            return true;
        }

        return false;
    }
}
