<?php

namespace App\Http\Middleware;

use App\Models\AcademicSession;
use App\Models\Notice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveAcademicSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $selected = null;

        if ($request->filled('session')) {
            $selected = $sessions->firstWhere('id', (int) $request->query('session'));
            if ($selected) {
                session(['selected_academic_session_id' => $selected->id]);
            }
        }

        if (! $selected) {
            $storedId = session('selected_academic_session_id');
            $selected = $storedId ? $sessions->firstWhere('id', $storedId) : null;
        }

        $selected ??= $sessions->firstWhere('is_current', true) ?? $sessions->first();

        if ($selected) {
            App::instance('currentAcademicSession', $selected);
        }

        View::share('academicSessions', $sessions);
        View::share('currentSession', $selected);
        View::share('topNotices', Notice::query()->published()->latest('publish_at')->limit(5)->get());

        return $next($request);
    }
}
