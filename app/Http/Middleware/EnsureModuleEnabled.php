<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleAccess $modules) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role_id || $user->isSuperAdmin()) {
            return $next($request);
        }

        // Student and parent accounts go straight to their portal, never to a locked-page.
        if (in_array($user->role?->slug, config('school.portal_only_roles', []), true)) {
            return redirect()->route($user->homeRouteName());
        }

        $routeName = $request->route()?->getName();

        if ($this->modules->canAccessRoute($user, $routeName)) {
            return $next($request);
        }

        $feature = $this->modules->featureForRoute($routeName);
        $menu = $this->modules->menuForRoute($routeName);
        $featureName = $menu['title'] ?? $this->modules->featureLabel($feature);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This feature is locked. Contact the super administrator to enable it.',
                'feature' => $feature,
            ], 403);
        }

        return response()->view('errors.module-locked', [
            'moduleKey' => $feature,
            'moduleName' => $featureName,
        ], 403);
    }
}
