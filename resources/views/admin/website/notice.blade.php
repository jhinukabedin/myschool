@extends('layouts.app')

@section('title', 'Notice')

@section('content')
    <x-page-header eyebrow="Website content" title="Notice"
        subtitle="Notices shown on the public homepage notice board. Creating and publishing is handled by the communication module, so this page is a read-only overview with quick links.">
        <x-slot:actions>
            @can('notices.manage')
                <x-button :href="route('notices.create')" variant="primary" icon="plus">New notice</x-button>
            @endcan
            <x-button :href="route('notices.index')" variant="secondary" icon="bell">Notice manager</x-button>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="layout-dashboard">Website dashboard</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="info-box" style="margin-bottom:16px">
        <strong>Tip:</strong> A notice appears on the public website once its status is <em>Published</em> and its publish date has passed. Use the notice manager to create, edit and publish notices for students, staff or everyone.
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">All notices</h3>
                <p class="card-sub">{{ $notices->total() }} notice(s)</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Publish at</th>
                        <th>Expires</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notices as $notice)
                        <tr>
                            <td>
                                <div class="cell-title">{{ $notice->title }}</div>
                                <div class="cell-sub">Created {{ $notice->created_at?->format('d M Y') }}@if ($notice->creator) · {{ $notice->creator->name }}@endif</div>
                            </td>
                            <td>{{ $notice->audience_label }}</td>
                            <td>
                                <x-badge :color="$notice->status === 'published' ? 'success' : ($notice->status === 'draft' ? 'warning' : 'neutral')">{{ ucfirst($notice->status) }}</x-badge>
                            </td>
                            <td class="cell-sub">{{ $notice->publish_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="cell-sub">{{ $notice->expires_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="actions">
                                @can('notices.manage')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('notices.edit', $notice) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No notices yet. Use “New notice” to create the first one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $notices->links() }}
    </div>
@endsection
