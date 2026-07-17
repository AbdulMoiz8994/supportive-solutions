<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $permission): Response
    {
        if (!auth()->check()) {
            return redirect('signin');
        }

        // Super Admin bypass
        if ($request->user()->isSuperAdmin()) {
            return $next($request);
        }

        if (!$request->user()->hasPermission($permission)) {
            abort(403, 'Unauthorized action. You do not have the required permission: ' . $permission);
        }

        return $next($request);
    }
}
