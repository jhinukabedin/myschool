<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Remember the visitor's language and return them to the current page.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $available = array_keys((array) config('app.available_locales', ['en' => 'English']));

        abort_unless(in_array($locale, $available, true), 404);

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        Cookie::queue(Cookie::forever('locale', $locale));

        return redirect()->back();
    }
}
