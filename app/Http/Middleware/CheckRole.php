<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect('signin');
        }

        foreach ($roles as $role) {
            // Check if user has the role
            if ($request->user()->role === $role) {
                return $next($request);
            }
            
            // Special case for Super Admin (can do anything)
            if ($request->user()->isSuperAdmin()) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
