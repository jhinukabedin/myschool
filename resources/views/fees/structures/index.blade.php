@extends('layouts.app')

@section('title', 'Fee Structure')

@section('content')
    <x-page-header eyebrow="Fees" title="Fee Structures" subtitle="Define class-wise fees for each academic session and assign them to students.">
        <x-slot:actions>
            @can('fees.manage')
                <x-button :href="route('fees.structures.create')" variant="primary" icon="plus">New structure</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Structures</h3><p class="card-sub">{{ $session?->name ?? 'All sessions' }}</p></div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field">
                    <select class="select" name="session_id">
                        <option value="">All sessions</option>
                        @foreach ($sessions as $sessionOption)
                            <option value="{{ $sessionOption->id }}" @selected($session?->id === $sessionOption->id)>{{ $sessionOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="class_id">
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Structure</th><th>Class</th><th>Session</th><th>Items</th><th class="num">Gross</th><th class="num">Scholarship</th><th class="num">Net</th><th>Effective</th><th>Status</th><th>Assigned</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($structures as $structure)
                        <tr>
                            <td><div class="cell-title">{{ $structure->name }}</div><div class="cell-sub">{{ Str::limit($structure->description, 50) }}</div></td>
                            <td>{{ $structure->class?->name }}</td>
                            <td>{{ $structure->academicSession?->name }}</td>
                            <td>{{ $structure->items->count() }}</td>
                            <td class="num">{{ money($structure->total) }}</td>
                            <td class="num {{ (float) $structure->scholarship_total > 0 ? 'text-success' : 'muted' }}">{{ (float) $structure->scholarship_total > 0 ? '-'.money($structure->scholarship_total) : '—' }}</td>
                            <td class="num">{{ money($structure->net_total) }}</td>
                            <td class="muted">{{ $structure->effective_from?->format('d M Y') }} – {{ $structure->effective_to?->format('d M Y') ?? 'Open' }}</td>
                            <td><x-badge :color="$structure->status === 'active' ? 'success' : 'muted'">{{ ucfirst($structure->status) }}</x-badge></td>
                            <td>{{ $structure->assignments_count }} row(s)</td>
                            <td class="actions">
                                @can('fees.manage')
                                    <a href="{{ route('fees.structures.edit', $structure) }}" class="btn-icon" title="Edit"><x-icon name="edit" :size="15" /></a>
                                    <form method="POST" action="{{ route('fees.structures.assign', $structure) }}" onsubmit="return confirm('Assign all eligible items from this structure to every active student in {{ $structure->class?->name }}? Existing rows are not duplicated.')">
                                        @csrf
                                        <button type="submit" class="btn-icon" title="Assign to class"><x-icon name="users" :size="15" /></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="table-empty">No fee structures found for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $structures->links() }}
    </div>
@endsection
