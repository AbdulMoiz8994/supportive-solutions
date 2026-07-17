<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo.routes_enabled')) {
            return $next($request);
        }

        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        abort(403, 'Demo and template pages are restricted.');
    }
}
