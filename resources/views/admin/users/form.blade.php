@extends('layouts.app')

@section('title', $user->exists ? 'Edit User' : 'New User')

@section('content')
    <x-page-header eyebrow="Admin" :title="$user->exists ? 'Edit User' : 'New User'" subtitle="Accounts are linked to a single role. Roles carry granular module permissions.">
        <x-slot:actions>
            <x-button :href="route('users.index')" variant="secondary" icon="arrow-left">Users</x-button>
        </x-slot:actions>
    </x-page-header>


    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Account Details</h3><p class="card-sub">{{ $user->exists ? 'Leave the password blank to keep the current one' : 'A username and default school password are created automatically when left blank' }}</p></div></div>
            <div class="form-section">
                <div class="form-grid">
                    <x-input name="name" label="Full name" :value="$user->name" required />
                    <x-input name="email" label="Email address (optional)" type="email" :value="$user->email" autocomplete="off" help="Optional for portal accounts. A username is enough to sign in." />
                    <x-input name="username" label="Username (optional)" :value="$user->username" autocomplete="off" help="Letters, numbers, dots, dashes and underscores. Generated from the email or name when blank." />
                    <x-input name="phone" label="Phone" :value="$user->phone" />
                    <x-select name="role_id" label="Role" :options="$roles->pluck('name', 'id')" :selected="$user->role_id" required />
                    <x-select name="staff_id" label="Linked staff profile" :options="$staffOptions->mapWithKeys(fn ($staff) => [$staff->id => $staff->full_name.' · '.$staff->employee_no])" :selected="$user->staff?->id" placeholder="Not linked" help="Teachers need an active staff link: assignment and marks checks run against it." />
                    <x-input name="password" label="Password" type="password" autocomplete="new-password" :help="$user->exists ? 'Leave blank to keep the existing password.' : 'Leave blank to use the configured default school password (School@12345 unless changed).'" />
                    <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
                    <label class="checkbox span-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
                        <span>Account is active and can sign in</span>
                    </label>
                </div>
            </div>
            <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                <x-button :href="route('users.index')" variant="ghost">Cancel</x-button>
                <x-button type="submit" variant="primary" icon="save">{{ $user->exists ? 'Save user' : 'Create user' }}</x-button>
            </div>
        </div>
    </form>
@endsection
