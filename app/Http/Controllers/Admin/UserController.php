<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\AccountProvisioningService;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly AccountProvisioningService $accounts
    ) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->with('role', 'staff')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($q) use ($request) {
                $term = $request->string('q');
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $user = new User(['is_active' => true]);

        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'staffOptions' => $this->staffOptions($user),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $staffId = $data['staff_id'] ?? null;
        unset($data['staff_id']);

        $usedDefaultPassword = blank($data['password'] ?? null);
        $password = $usedDefaultPassword ? portal_default_password() : $data['password'];
        $username = filled($data['username'] ?? null)
            ? Str::lower($data['username'])
            : $this->accounts->uniqueUsername($data['email'] ?: $data['name']);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['email'] = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;
        $data['username'] = $username;
        $data['phone_normalized'] = normalize_phone($data['phone'] ?? null);
        $data['password'] = $password;
        $data['email_verified_at'] = $data['email'] ? now() : null;

        $user = DB::transaction(function () use ($data, $staffId) {
            $user = User::create($data);

            if ($staffId) {
                Staff::query()->whereKey($staffId)->update(['user_id' => $user->id]);
            }

            return $user;
        });

        $this->audit->record(
            'user_created',
            'Administration',
            "User account {$user->name} ({$user->username}) created with role {$user->role?->name}",
            $user,
            null,
            ['username' => $user->username, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => $user->is_active, 'staff_id' => $staffId]
        );

        $message = "User account created. Login: {$user->username}.";
        if ($usedDefaultPassword) {
            $message .= ' Initial password: '.portal_default_password().'. Ask the user to change it after signing in.';
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'staffOptions' => $this->staffOptions($user),
        ]);
    }

    public function update(StoreUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $staffId = $data['staff_id'] ?? null;
        unset($data['staff_id']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if (filled($data['username'] ?? null)) {
            $data['username'] = Str::lower($data['username']);
        } else {
            unset($data['username']);
        }

        $data['email'] = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;

        if (array_key_exists('phone', $data)) {
            $data['phone_normalized'] = normalize_phone($data['phone']);
        }

        if ($user->id === $request->user()->id && ! $data['is_active']) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $before = $user->only(['name', 'email', 'username', 'role_id', 'is_active']);

        DB::transaction(function () use ($user, $data, $staffId) {
            $user->update($data);

            // Releasing the previous profile keeps the staff record intact.
            Staff::query()
                ->where('user_id', $user->id)
                ->when($staffId, fn ($query) => $query->whereKeyNot($staffId))
                ->update(['user_id' => null]);

            if ($staffId) {
                Staff::query()->whereKey($staffId)->update(['user_id' => $user->id]);
            }
        });

        $this->audit->record('user_updated', 'Administration', "User account {$user->name} updated", $user, $before, $user->only(['name', 'email', 'username', 'role_id', 'is_active']) + ['staff_id' => $staffId]);

        return redirect()->route('users.index')->with('success', 'User account updated.');
    }

    /**
     * Staff profiles that can be linked to this account: unlinked profiles plus
     * the one already attached, so the selector never steals another user's link.
     */
    private function staffOptions(User $user)
    {
        return Staff::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id');

                if ($user->exists) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own active status.');
        }

        $before = $user->only(['is_active']);
        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->record('user_status_changed', 'Administration', "User {$user->name} ".($user->is_active ? 'activated' : 'deactivated'), $user, $before, ['is_active' => $user->is_active]);

        return back()->with('success', 'User status updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $before = $user->only(['name', 'email', 'is_active']);
        $user->delete();

        $this->audit->record('user_deleted', 'Administration', "User account {$user->name} deleted (soft delete)", $user, $before, null);

        return back()->with('success', 'User account deleted. Historical audit records are preserved.');
    }
}
