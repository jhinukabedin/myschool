@extends('layouts.app')

@section('title', 'User Access')

@section('content')
    <x-page-header eyebrow="Administration" title="User Access"
        subtitle="Give each staff member only the permissions they need. Nobody gets create, edit or delete rights until they are enabled here.">
        <x-slot:actions>
            @if ($isSuper)
                <x-button :href="route('roles.index')" variant="secondary" icon="key">School administrator permissions</x-button>
            @endif
            <x-button :href="route('subscription.index')" variant="secondary" icon="layout-dashboard">Subscription &amp; modules</x-button>
        </x-slot:actions>
    </x-page-header>

    <style>
        .access-layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px;align-items:start}
        .access-users{max-height:70vh;overflow:auto;padding:6px}
        .access-user{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:inherit;border:1px solid transparent}
        .access-user:hover{background:rgba(127,127,140,.08)}
        .access-user.is-active{border-color:#7c5cff;background:rgba(124,92,255,.08)}
        .access-user small{display:block;color:var(--muted,#6b7280)}
        .access-count{margin-left:auto;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;background:rgba(127,127,140,.14)}
        .access-count.has{background:rgba(22,163,74,.14);color:#15803d}
        .perm-group{border:1px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden}
        .perm-group-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:rgba(127,127,140,.08);font-weight:700}
        .perm-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-top:1px solid var(--border);cursor:pointer}
        .perm-row.is-disabled{cursor:not-allowed;opacity:.55}
        .perm-row input{width:18px;height:18px;margin:0;flex:none}
        .perm-name{flex:1;min-width:0}
        .perm-slug{font-size:11px;color:var(--muted,#6b7280);font-family:monospace}
        @media (max-width:900px){.access-layout{grid-template-columns:1fr}}
    </style>

    @if (! $isSuper)
        <div class="info-box" style="margin-bottom:16px;border-left:4px solid #7c5cff">
            <strong>You can give only the permissions you hold.</strong>
            Permissions shown greyed out belong to the super administrator and cannot be given by you.
            Your own permissions are set by the super administrator.
        </div>
    @endif

    @if ($users->isEmpty())
        <div class="card"><div class="empty-state">No staff accounts to manage yet. Create a staff user first.</div></div>
    @else
        <div class="access-layout">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Staff accounts</h3>
                        <p class="card-sub">{{ $users->count() }} account(s)</p>
                    </div>
                </div>
                <div class="access-users">
                    @foreach ($users as $user)
                        @php($count = $user->explicitPermissions()->count())
                        <a href="{{ route('access.index', ['user' => $user->id]) }}"
                           class="access-user {{ $selected?->id === $user->id ? 'is-active' : '' }}">
                            <span class="table-avatar">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1) }}</span>
                            <span style="min-width:0">
                                <strong>{{ $user->name }}</strong>
                                <small>{{ $user->role?->name ?? 'No role' }}@if (! $user->is_active) · inactive @endif</small>
                            </span>
                            <span class="access-count {{ $count ? 'has' : '' }}">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($selected)
                <div class="stack">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ $selected->name }}</h3>
                                <p class="card-sub">{{ $selected->role?->name }} · tick the permissions this person may use</p>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <form method="POST" action="{{ route('access.template', $selected) }}" onsubmit="return confirm('Replace the ticked permissions with the {{ $selected->role?->name }} template? You will still need to save.')">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Apply role template</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('access.update', $selected) }}">
                        @csrf
                        @method('PUT')

                        @foreach ($groups as $group)
                            <div class="perm-group">
                                <div class="perm-group-head">
                                    <span>{{ $group['module'] }}</span>
                                    <span class="muted" style="font-weight:600">
                                        {{ collect($group['items'])->where('checked', true)->count() }} / {{ count($group['items']) }}
                                    </span>
                                </div>
                                @foreach ($group['items'] as $item)
                                    <label class="perm-row {{ $item['allowed'] ? '' : 'is-disabled' }}">
                                        <input type="checkbox" name="permissions[]" value="{{ $item['slug'] }}"
                                               @checked($item['checked']) @disabled(! $item['allowed'])>
                                        <span class="perm-name">
                                            {{ $item['name'] }}
                                            <span class="perm-slug" style="display:block">{{ $item['slug'] }}</span>
                                        </span>
                                        @unless ($item['allowed'])
                                            <x-icon name="lock" :size="14" />
                                        @endunless
                                    </label>
                                @endforeach
                            </div>
                        @endforeach

                        <div class="card" style="position:sticky;bottom:12px;margin-top:8px">
                            <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px">
                                <x-button type="submit" variant="primary" icon="save">Save permissions</x-button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @endif
@endsection
