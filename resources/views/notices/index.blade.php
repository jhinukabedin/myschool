@extends('layouts.app')

@section('title', 'Notices')

@section('content')
    <x-page-header eyebrow="Communication" title="Notices" subtitle="Publish announcements to the whole school, students, staff or selected classes.">
        <x-slot:actions>
            @can('notices.manage')
                <x-button :href="route('notices.create')" variant="primary" icon="plus">New notice</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card label="Published" :value="number_format($counts['published'] ?? 0)" icon="bell" tone="collection" meta="Visible to their audience" />
        <x-stat-card label="Drafts" :value="number_format($counts['draft'] ?? 0)" icon="file-text" tone="pending" meta="Not yet published" />
        <x-stat-card label="Archived" :value="number_format($counts['archived'] ?? 0)" icon="archive" tone="expense" meta="Retained for reference" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">All Notices</h3><p class="card-sub">{{ $notices->total() }} notice(s)</p></div>
            <form method="GET" action="{{ route('notices.index') }}" class="filter-bar" style="margin:0">
                <div class="field grow"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search notices..." aria-label="Search notices"></div>
                <div class="field">
                    <select class="select" name="status" aria-label="Filter status">
                        <option value="">Any status</option>
                        @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Notice</th><th>Audience</th><th>Status</th><th>Publish at</th><th>Expires</th><th>Created by</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($notices as $notice)
                        <tr>
                            <td style="max-width:380px">
                                <div class="cell-title">{{ $notice->title }}</div>
                                <div class="cell-sub">{{ Str::limit($notice->plain_body, 110) }}</div>
                            </td>
                            <td>
                                <div>{{ $notice->audience_label }}</div>
                                @if ($notice->audience === 'classes' && $notice->classes->isNotEmpty())
                                    <div class="cell-sub">{{ $notice->classes->pluck('name')->implode(', ') }}</div>
                                @endif
                            </td>
                            <td>
                                <x-badge :color="$notice->status === 'published' ? 'success' : ($notice->status === 'draft' ? 'warning' : 'muted')">{{ ucfirst($notice->status) }}</x-badge>
                            </td>
                            <td>{{ $notice->publish_at?->format('d M Y, h:i A') ?? '—' }}</td>
                            <td>{{ $notice->expires_at?->format('d M Y, h:i A') ?? 'No expiry' }}</td>
                            <td>
                                <div>{{ $notice->creator?->name ?? 'System' }}</div>
                                <div class="cell-sub">{{ $notice->created_at?->format('d M Y') }}</div>
                            </td>
                            <td class="actions">
                                @can('notices.manage')
                                    @if ($notice->status !== 'published')
                                        <form method="POST" action="{{ route('notices.publish', $notice) }}" onsubmit="return confirm('Publish this notice now?')">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm"><x-icon name="send" :size="13" /> Publish</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('notices.edit', $notice) }}" class="btn-icon" title="Edit notice"><x-icon name="pencil" :size="15" /></a>
                                    <form method="POST" action="{{ route('notices.destroy', $notice) }}" onsubmit="return confirm('Delete this notice? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="Delete notice"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No notices match the current filters. Create a notice to communicate with the school.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $notices->links() }}
    </div>
@endsection
