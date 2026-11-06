<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the interface language for the current request.
     *
     * Priority: explicit session choice, remember-me cookie, application
     * default. Unknown values always fall back to the configured default so a
     * tampered cookie can never break translations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys((array) config('app.available_locales', ['en' => 'English']));
        $locale = $request->session()->get('locale') ?? $request->cookie('locale') ?? config('app.locale');

        if (! in_array($locale, $available, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
