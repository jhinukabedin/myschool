<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function edit(): View
    {
        $role = auth()->user()?->role?->slug;

        return in_array($role, ['student', 'parent', 'teacher'], true)
            ? view('portal.password')
            : view('auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update(['password' => $validated['password']]);

        $this->audit->record('password_changed', 'Security', 'Password changed from the account settings page', $user);

        return redirect()->route('password.change')->with('success', 'Your password has been updated.');
    }
}
