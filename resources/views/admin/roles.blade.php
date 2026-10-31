@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <x-page-header eyebrow="Admin" title="Roles & Permissions" subtitle="Permissions are granted per role. The Super Administrator role always has unrestricted access.">
        <x-slot:actions>
            <x-button :href="route('users.index')" variant="secondary" icon="users">User accounts</x-button>
            <x-button :href="route('admin.index')" variant="ghost" icon="arrow-left">Administration</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="stack">
        @foreach ($roles as $role)
            @php($selectedPermissions = $rolePermissions[$role->id] ?? [])
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ $role->name }} <span class="mono muted" style="font-size:10px">{{ $role->slug }}</span></h3>
                        <p class="card-sub">{{ $role->description }} · {{ $role->users_count }} user(s)</p>
                    </div>
                    @if ($role->slug === 'super-admin')
                        <x-badge color="success">Unrestricted</x-badge>
                    @else
                        <x-badge color="violet">{{ count($selectedPermissions) }} permission(s)</x-badge>
                    @endif
                </div>

                @if ($role->slug === 'super-admin')
                    <div class="card-body">
                        <div class="info-box">This role bypasses all permission checks and cannot be limited. Assign it only to trusted system owners.</div>
                    </div>
                @else
                    <form method="POST" action="{{ route('roles.update', $role) }}">
                        @csrf
                        @method('PUT')
                        <div class="form-section">
                            <div class="grid-2">
                                @foreach ($permissions as $module => $modulePermissions)
                                    <div>
                                        <div class="form-section-title" style="margin-bottom:10px">{{ $module }}</div>
                                        <div class="stack" style="gap:8px">
                                            @foreach ($modulePermissions as $permission)
                                                <label class="checkbox">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selectedPermissions))>
                                                    <span>{{ $permission->name }} <span class="muted-2 mono" style="font-size:10px">{{ $permission->slug }}</span></span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-section" style="display:flex;justify-content:flex-end">
                            <x-button type="submit" variant="primary" icon="save">Save permissions</x-button>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endsection
