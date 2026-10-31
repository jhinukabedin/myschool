<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\LoginIdentifierResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly LoginIdentifierResolver $identifiers
    ) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRouteName());
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $key = $request->filled('identifier') && ! $request->filled('email') ? 'identifier' : 'email';

        $credentials = $request->validate([
            $key => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        $identifier = (string) ($request->input('identifier') ?: $credentials[$key]);
        $user = $this->identifiers->resolve($identifier);

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->record(
                'login_failed',
                'Security',
                'Failed login attempt for '.$identifier
            );

            throw ValidationException::withMessages([
                $key => 'These credentials do not match our records, or the account is inactive.',
            ]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('login', 'Security', "{$user->name} signed in", $user);

        return redirect()->intended(route($user->homeRouteName()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $redirect = $user?->role?->slug === 'student'
            ? 'student.login'
            : ($user?->role?->slug === 'parent' ? 'parent.login' : 'login');
        $this->audit->record('logout', 'Security', ($user?->name ?? 'User').' signed out', $user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($redirect)->with('status', 'You have been signed out.');
    }
}
