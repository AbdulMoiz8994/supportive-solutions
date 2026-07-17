<?php

namespace App\Http\Middleware;

use App\Services\GlobalSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyGlobalSettings
{
    public function __construct(
        protected GlobalSettingsService $settingsService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = $this->settingsService->runtime();

            config([
                'session.lifetime' => (int) $settings['security.session_timeout_minutes'],
                'uploads.max_kilobytes' => (int) $settings['uploads.max_file_size_kb'],
            ]);
        } catch (\Throwable) {
            // Database may be unavailable during install/migrate.
        }

        return $next($request);
    }
}
