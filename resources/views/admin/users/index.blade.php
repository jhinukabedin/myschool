@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-page-header eyebrow="Admin" title="User Accounts" subtitle="Create accounts, assign roles and control access to the school system.">
        <x-slot:actions>
            <x-button :href="route('users.create')" variant="primary" icon="user-plus">New user</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Accounts</h3><p class="card-sub">{{ $users->total() }} user(s)</p></div>
            <form method="GET" action="{{ route('users.index') }}" class="filter-bar" style="margin:0">
                <div class="field grow" style="min-width:200px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, username, email or phone"></div>
                <div class="field">
                    <select class="select" name="role_id">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>User</th><th class="hide-lg">Contact</th><th class="hide-sm">Role</th><th class="hide-lg">Linked staff</th><th class="hide-sm">Status</th><th class="hide-md">Last login</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="cell-main">
                                    <span class="table-avatar">{{ $user->initials }}</span>
                                    <div>
                                        <div class="cell-title">{{ $user->name }}</div>
                                        <div class="cell-sub">{{ $user->username ? '@'.$user->username : '' }}{{ $user->username && $user->email ? ' · ' : '' }}{{ $user->email }}</div>
                                        <div class="cell-sub mobile-meta">
                                            <x-badge color="violet">{{ $user->role?->name ?? 'No role' }}</x-badge>
                                            <x-badge :color="$user->is_active ? 'success' : 'muted'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hide-lg">{{ $user->phone ?? '—' }}</td>
                            <td class="hide-sm"><x-badge color="violet">{{ $user->role?->name ?? 'No role' }}</x-badge></td>
                            <td class="hide-lg">{{ $user->staff?->full_name ?? '—' }}</td>
                            <td class="hide-sm"><x-badge :color="$user->is_active ? 'success' : 'muted'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td class="muted hide-md">{{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}</td>
                            <td class="actions">
                                <a href="{{ route('users.edit', $user) }}" class="btn-icon" title="Edit"><x-icon name="edit" :size="15" /></a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.toggle', $user) }}">
                                        @csrf
                                        <button type="submit" class="btn-icon" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                            <x-icon :name="$user->is_active ? 'lock' : 'check-circle'" :size="15" />
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user account? The account is soft-deleted and audit records are preserved.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="Delete"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No user accounts match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
