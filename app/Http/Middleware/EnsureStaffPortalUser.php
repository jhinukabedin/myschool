<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPortalUser
{
    /**
     * Staff portal boundary: any active user linked to a staff record may enter
     * (teacher, accountant, librarian, admin staff…). Record ownership is
     * enforced by the controller itself.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->staff) {
            abort(403, 'This area is only available to staff accounts.');
        }

        return $next($request);
    }
}
