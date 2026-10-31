<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalRole
{
    /**
     * Restrict a route group to one portal audience. This is an authentication
     * boundary; record-level ownership is still checked by each controller.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || $user->role?->slug !== $role) {
            abort(403, 'This area is not available to your account.');
        }

        return $next($request);
    }
}
