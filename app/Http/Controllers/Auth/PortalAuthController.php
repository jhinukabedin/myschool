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

class PortalAuthController extends Controller
{
    private const PORTALS = [
        'student' => ['role' => 'student', 'title' => 'Student Portal', 'route' => 'student.dashboard'],
        'parent' => ['role' => 'parent', 'title' => 'Parent Portal', 'route' => 'parent.dashboard'],
        'teacher' => ['role' => 'teacher', 'title' => 'Teacher Portal', 'route' => 'teacher.dashboard'],
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly LoginIdentifierResolver $identifiers
    ) {}

    public function showStudent(): View|RedirectResponse
    {
        return $this->show('student');
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        return $this->login($request, 'student');
    }

    public function showParent(): View|RedirectResponse
    {
        return $this->show('parent');
    }

    public function storeParent(Request $request): RedirectResponse
    {
        return $this->login($request, 'parent');
    }

    public function showTeacher(): View|RedirectResponse
    {
        return $this->show('teacher');
    }

    public function storeTeacher(Request $request): RedirectResponse
    {
        return $this->login($request, 'teacher');
    }

    private function show(string $portal): View|RedirectResponse
    {
        $meta = self::PORTALS[$portal];

        if (Auth::check()) {
            $user = Auth::user();

            return redirect()->route(
                $user->role?->slug === $meta['role'] ? $meta['route'] : $user->homeRouteName()
            );
        }

        return view('auth.portal-login', [
            'portal' => $portal,
            'title' => $meta['title'],
            'action' => route($portal.'.login.store'),
        ]);
    }

    private function login(Request $request, string $portal): RedirectResponse
    {
        $meta = self::PORTALS[$portal];

        $key = $request->filled('identifier') && ! $request->filled('email') ? 'identifier' : 'email';

        $credentials = $request->validate([
            $key => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        $identifier = (string) ($request->input('identifier') ?: $credentials[$key]);
        $user = $this->identifiers->resolve($identifier, $meta['role']);

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->record(
                'portal_login_failed',
                'Security',
                "Failed {$portal} portal login attempt for {$identifier}"
            );

            throw ValidationException::withMessages([
                $key => 'These credentials do not match an active account.',
            ]);
        }

        if ($user->role?->slug !== $meta['role']) {
            $this->audit->record(
                'portal_login_role_rejected',
                'Security',
                "{$user->name} attempted to use the {$portal} portal with an incompatible role",
                $user
            );

            throw ValidationException::withMessages([
                $key => 'This account does not have access to the '.$meta['title'].'.',
            ]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('portal_login', 'Security', "{$user->name} signed in to the {$portal} portal", $user);

        return redirect()->intended(route($meta['route']));
    }
}
