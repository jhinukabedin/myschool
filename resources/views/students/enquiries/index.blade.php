@extends('layouts.app')

@section('title', 'Admission Enquiries')

@section('content')
    <x-page-header eyebrow="Students" title="Admission Enquiries" subtitle="Track prospective families from first contact through admission.">
        <x-slot:actions>
            @can('enquiries.manage')
                <x-button :href="route('enquiries.create')" variant="primary" icon="plus">New enquiry</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="New" :value="$counts['new'] ?? 0" icon="message-circle" tone="student" />
        <x-stat-card label="Follow-up" :value="$counts['follow_up'] ?? 0" icon="clock" tone="pending" />
        <x-stat-card label="Admitted" :value="$counts['admitted'] ?? 0" icon="user-check" tone="collection" />
        <x-stat-card label="Closed" :value="$counts['closed'] ?? 0" icon="archive" tone="expense" />
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Enquiry Register</h3>
                <p class="card-sub">Most recent first</p>
            </div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field" style="min-width:180px">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, phone, number">
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">All statuses</option>
                        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'follow_up' => 'Follow-up', 'admitted' => 'Admitted', 'closed' => 'Closed'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Enquiry</th><th>Prospective student</th><th>Class</th><th>Follow-up</th><th>Status</th><th>Source</th><th class="actions">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($enquiries as $enquiry)
                        <tr>
                            <td><span class="mono" style="font-size:11px">{{ $enquiry->enquiry_no }}</span></td>
                            <td>
                                <div class="cell-title">{{ $enquiry->student_name }}</div>
                                <div class="cell-sub">{{ $enquiry->guardian_name }} · {{ $enquiry->phone }}</div>
                            </td>
                            <td>{{ $enquiry->schoolClass?->name ?? '—' }}</td>
                            <td>{{ $enquiry->follow_up_date?->format('d M Y') ?? '—' }}</td>
                            <td><x-badge :color="$enquiry->status_color">{{ str_replace('_', ' ', ucfirst($enquiry->status)) }}</x-badge></td>
                            <td class="muted">{{ $enquiry->source ?? '—' }}</td>
                            <td class="actions">
                                @can('enquiries.manage')
                                    <a href="{{ route('enquiries.edit', $enquiry) }}" class="btn-icon" title="Edit enquiry"><x-icon name="edit" :size="15" /></a>
                                @endcan
                                @if ($enquiry->status !== 'admitted')
                                    @can('students.create')
                                        <a href="{{ route('admissions.create', ['enquiry' => $enquiry->id]) }}" class="btn-icon" title="Admit student"><x-icon name="user-plus" :size="15" /></a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No enquiries match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $enquiries->links() }}
    </div>
@endsection
