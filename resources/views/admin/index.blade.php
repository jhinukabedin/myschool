@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    <x-page-header eyebrow="Admin" title="Administration" :subtitle="$session ? 'System configuration for the '.$session->name.' academic session.' : 'Configure academic sessions and school settings.'">
        <x-slot:actions>
            @can('settings.manage')
                <x-button :href="route('settings.index')" variant="secondary" icon="settings">School settings</x-button>
            @endcan
            @can('academics.manage')
                <x-button :href="route('sessions.index')" variant="primary" icon="calendar">Manage sessions</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>


    <div class="stat-grid">
        <x-stat-card label="Academic sessions" :value="number_format($stats['sessions'])" icon="calendar" tone="student" :meta="$session?->name.' is current'" />
        <x-stat-card label="Classes" :value="number_format($stats['classes'])" icon="building" tone="collection" meta="Configured classes" />
        <x-stat-card label="Student records" :value="number_format($stats['students'])" icon="users" tone="expense" meta="Including archived" />
        <x-stat-card label="User accounts" :value="number_format($stats['users'])" icon="user-cog" tone="pending" :meta="$stats['staff'].' staff profiles'" />
    </div>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Configuration</h3><p class="card-sub">Jump to a management area</p></div></div>
                <div class="card-body">
                    <div class="quick-grid">
                        @can('academics.manage')
                            <a href="{{ route('sessions.index') }}" class="quick-item">
                                <span class="quick-icon violet"><x-icon name="calendar" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Sessions</span><span class="quick-sub">Academic years</span></span>
                            </a>
                            <a href="{{ route('classes.index') }}" class="quick-item">
                                <span class="quick-icon blue"><x-icon name="building" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Classes</span><span class="quick-sub">Grades & sections</span></span>
                            </a>
                            <a href="{{ route('sections.index') }}" class="quick-item">
                                <span class="quick-icon cyan"><x-icon name="list-checks" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Sections</span><span class="quick-sub">Class groups</span></span>
                            </a>
                        @endcan
                        @can('users.manage')
                            <a href="{{ route('users.index') }}" class="quick-item">
                                <span class="quick-icon green"><x-icon name="users" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Users</span><span class="quick-sub">Accounts & access</span></span>
                            </a>
                            <a href="{{ route('roles.index') }}" class="quick-item">
                                <span class="quick-icon amber"><x-icon name="shield" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Roles</span><span class="quick-sub">Permissions</span></span>
                            </a>
                        @endcan
                        @can('settings.manage')
                            <a href="{{ route('settings.index') }}" class="quick-item">
                                <span class="quick-icon rose"><x-icon name="settings" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Settings</span><span class="quick-sub">School profile</span></span>
                            </a>
                        @endcan
                        @can('audit.view')
                            <a href="{{ route('audit.index') }}" class="quick-item">
                                <span class="quick-icon violet"><x-icon name="file-text" :size="17" /></span>
                                <span class="quick-body"><span class="quick-title">Audit log</span><span class="quick-sub">Activity trail</span></span>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">Recent Activity</h3><p class="card-sub">Latest audited actions</p></div>
                    @can('audit.view')
                        <a href="{{ route('audit.index') }}" class="btn btn-ghost btn-sm">Full log <x-icon name="arrow-right" :size="13" /></a>
                    @endcan
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Description</th></tr></thead>
                        <tbody>
                            @forelse ($recentAudit as $log)
                                <tr>
                                    <td class="muted">{{ $log->created_at?->format('d M, h:i A') }}</td>
                                    <td>{{ $log->user?->name ?? 'System' }}</td>
                                    <td><x-badge color="violet">{{ $log->module }}</x-badge></td>
                                    <td class="mono" style="font-size:11px">{{ $log->action }}</td>
                                    <td style="white-space:normal;min-width:240px">{{ $log->description }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">No audit events have been recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">System Totals</h3></div></div>
                <div class="card-body">
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Staff profiles</span><span class="detail-value">{{ number_format($stats['staff']) }}</span></div>
                        <div class="detail-row"><span class="detail-label">Audit events</span><span class="detail-value">{{ number_format($stats['audit_events']) }}</span></div>
                        <div class="detail-row"><span class="detail-label">Current session</span><span class="detail-value">{{ $session?->name ?? 'Not configured' }}</span></div>
                        <div class="detail-row"><span class="detail-label">Session status</span><span class="detail-value">{{ $session ? ucfirst($session->status) : '—' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">Newest Users</h3><p class="card-sub">Recently created accounts</p></div>
                    @can('users.manage')
                        <a href="{{ route('users.create') }}" class="btn-icon" title="Add user"><x-icon name="plus" :size="15" /></a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($latestUsers as $user)
                        <div class="due-row">
                            <div class="due-info">
                                <span class="table-avatar">{{ $user->initials }}</span>
                                <div>
                                    <div class="cell-title">{{ $user->name }}</div>
                                    <div class="cell-sub">{{ $user->email }} · {{ $user->role?->name ?? 'No role' }}</div>
                                </div>
                            </div>
                            <x-badge :color="$user->is_active ? 'success' : 'muted'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge>
                        </div>
                    @empty
                        <p class="muted" style="font-size:12px;margin:0">No users have been created yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
