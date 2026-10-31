@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
    <x-page-header eyebrow="Admin" title="Audit Log" subtitle="An immutable trail of administrative, academic and financial actions.">
        <x-slot:actions>
            <x-button :href="route('admin.index')" variant="secondary" icon="arrow-left">Administration</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Activity</h3><p class="card-sub">{{ $logs->total() }} event(s)</p></div>
            <form method="GET" action="{{ route('audit.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field grow" style="min-width:170px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search description or action"></div>
                <div class="field">
                    <select class="select" name="module">
                        <option value="">All modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="user_id">
                        <option value="">All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><input class="input" type="date" name="from" value="{{ request('from') }}" aria-label="From date"></div>
                <div class="field"><input class="input" type="date" name="to" value="{{ request('to') }}" aria-label="To date"></div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Description</th><th>IP</th><th>Changes</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="muted">{{ $log->created_at?->format('d M Y, h:i:s A') }}</td>
                            <td>
                                <div class="cell-title">{{ $log->user?->name ?? 'System' }}</div>
                                <div class="cell-sub">{{ $log->user?->email ?? 'Automated' }}</div>
                            </td>
                            <td><x-badge color="violet">{{ $log->module }}</x-badge></td>
                            <td class="mono" style="font-size:11px">{{ $log->action }}</td>
                            <td style="white-space:normal;min-width:260px">{{ $log->description }}</td>
                            <td class="muted mono" style="font-size:10.5px">{{ $log->ip_address ?? '—' }}</td>
                            <td>
                                @if ($log->old_values || $log->new_values)
                                    <details>
                                        <summary class="muted" style="cursor:pointer;font-size:11px">View</summary>
                                        <pre class="mono" style="font-size:10px;white-space:pre-wrap;max-width:420px;margin:6px 0 0;color:#cbbfe5">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    <span class="muted-2">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No audit events match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
