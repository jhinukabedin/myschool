@extends('layouts.app')

@section('title', 'Class-Subject Map')

@section('content')
    <x-page-header eyebrow="Academics" title="Class-Subject Map" :subtitle="$session ? 'Subjects offered by each class in the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('subjects.index')" variant="secondary" icon="book-open">Subjects</x-button>
            <x-button :href="route('grade-rules.index')" variant="secondary" icon="percent">Grade rules</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-side-main">
        @can('academics.manage')
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Map a Subject</h3><p class="card-sub">Duplicate mappings are rejected</p></div></div>
                <form method="POST" action="{{ route('class-subjects.store') }}">
                    @csrf
                    <div class="form-section">
                        <div class="stack">
                            <x-select name="academic_session_id" label="Academic session" :options="$sessions->pluck('name', 'id')" :selected="$session?->id" required />
                            <x-select name="class_id" label="Class" :options="$classes->pluck('name', 'id')" required />
                            <x-select name="subject_id" label="Subject" :options="$subjects->pluck('name', 'id')" required />
                            <x-button type="submit" variant="primary" icon="plus" class="btn-block">Add mapping</x-button>
                        </div>
                    </div>
                </form>
            </div>
        @endcan

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Mappings</h3><p class="card-sub">{{ $mappings->total() }} mapping(s)</p></div>
                <form method="GET" action="{{ route('class-subjects.index') }}" class="filter-bar" style="margin:0">
                    <div class="field">
                        <select class="select" name="class_id">
                            <option value="">All classes</option>
                            @foreach ($classes as $classOption)
                                <option value="{{ $classOption->id }}" @selected($class?->id === $classOption->id)>{{ $classOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Class</th><th>Subject</th><th>Session</th><th>Mapped</th><th class="actions"></th></tr></thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr>
                                <td class="cell-title">{{ $mapping->class?->name }}</td>
                                <td>{{ $mapping->subject?->name }}</td>
                                <td>{{ $mapping->academicSession?->name }}</td>
                                <td class="muted">{{ $mapping->created_at?->format('d M Y') }}</td>
                                <td class="actions">
                                    @can('academics.manage')
                                        <form method="POST" action="{{ route('class-subjects.destroy', $mapping) }}" onsubmit="return confirm('Remove this mapping? Existing exam records are unaffected.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Remove"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">No class-subject mappings for these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $mappings->links() }}
        </div>
    </div>
@endsection
