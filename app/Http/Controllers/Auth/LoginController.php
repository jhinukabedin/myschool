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

        // Student and parent accounts sign in only through their portal pages.
        if (in_array($user->role?->slug, config('school.portal_only_roles', []), true)) {
            $this->audit->record('login_portal_redirect', 'Security', "{$user->name} tried the staff login", $user);

            throw ValidationException::withMessages([
                $key => 'Student and parent accounts sign in from the portal login page.',
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

        // A super administrator who is checking another account must never lose
        // their own session by pressing Sign out inside the impersonated view.
        if ($impersonatorId = $request->session()->get('impersonator_id')) {
            $impersonator = \App\Models\User::query()->find($impersonatorId);

            if ($impersonator && $impersonator->isSuperAdmin()) {
                $targetName = $user?->name ?? 'another user';

                $request->session()->forget('impersonator_id');
                Auth::login($impersonator);
                $request->session()->regenerate();

                $this->audit->record(
                    'impersonation_stopped',
                    'Security',
                    "{$impersonator->name} returned from viewing as {$targetName}",
                    $user,
                    null,
                    ['impersonator_id' => $impersonator->id]
                );

                return redirect()->route('dashboard')
                    ->with('success', 'You are back in your own account.');
            }
        }

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
