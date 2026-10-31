<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403, 'Your account is not active.');

        foreach ($permissions as $permission) {
            if (str_contains($permission, '|')) {
                $any = collect(explode('|', $permission))->contains(fn ($p) => $user->hasPermission($p));
                if ($any) {
                    return $next($request);
                }
            } elseif ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
