<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets the super administrator sign in as another user to check exactly what
 * that person sees, then return to the super administrator account.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin?->isSuperAdmin(), 403, 'Only the super administrator can use this feature.');
        abort_if($admin->is($user), 403, 'You cannot log in as yourself.');
        abort_unless($user->is_active, 403, 'This account is inactive and cannot be used.');

        if ($request->session()->has('impersonator_id')) {
            return back()->with('warning', 'Return to your own account before logging in as someone else.');
        }

        $request->session()->put('impersonator_id', $admin->id);

        $this->audit->record(
            'impersonation_started',
            'Security',
            "{$admin->name} logged in as {$user->name}",
            $user,
            null,
            ['impersonator_id' => $admin->id, 'user_id' => $user->id]
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->homeRouteName())
            ->with('status', "You are now viewing the system as {$user->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        abort_unless($impersonatorId, 403, 'You are not logged in as another user.');

        $impersonator = User::query()->find($impersonatorId);
        $target = $request->user();

        if (! $impersonator || ! $impersonator->isSuperAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'The original super administrator account is no longer available.');
        }

        $request->session()->forget('impersonator_id');

        Auth::login($impersonator);
        $request->session()->regenerate();

        $this->audit->record(
            'impersonation_stopped',
            'Security',
            "{$impersonator->name} returned from viewing as ".($target?->name ?? 'another user'),
            $target,
            null,
            ['impersonator_id' => $impersonator->id]
        );

        return redirect()->route('dashboard')
            ->with('success', 'You are back in your own account.');
    }
}
