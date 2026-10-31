@extends('layouts.app')

@section('title', 'Subjects')

@section('content')
    <x-page-header eyebrow="Academics" title="Subjects" subtitle="Manage the subject catalogue used by class mappings, examinations and marks.">
        <x-slot:actions>
            <x-button :href="route('class-subjects.index')" variant="secondary" icon="list-checks">Class-subject map</x-button>
            @can('academics.manage')
                <x-button :href="route('subjects.create')" variant="primary" icon="plus">New subject</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Subject Catalogue</h3><p class="card-sub">{{ $subjects->total() }} subject(s)</p></div>
            <form method="GET" action="{{ route('subjects.index') }}" class="filter-bar" style="margin:0">
                <div class="field grow" style="min-width:220px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name or code"></div>
                <x-button type="submit" variant="secondary" icon="filter">Search</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Subject</th><th>Type</th><th class="num">Full marks</th><th class="num">Pass marks</th><th class="num">Class maps</th><th class="num">Exam uses</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td class="mono">{{ $subject->code }}</td>
                            <td class="cell-title">{{ $subject->name }}</td>
                            <td>{{ ucfirst($subject->type) }}</td>
                            <td class="num">{{ number_format((float) $subject->full_marks, 2) }}</td>
                            <td class="num">{{ number_format((float) $subject->pass_marks, 2) }}</td>
                            <td class="num">{{ $subject->class_subjects_count }}</td>
                            <td class="num">{{ $subject->examination_subjects_count }}</td>
                            <td class="actions">
                                @can('academics.manage')
                                    <a href="{{ route('subjects.edit', $subject) }}" class="btn-icon" title="Edit"><x-icon name="edit" :size="15" /></a>
                                    <form method="POST" action="{{ route('subjects.destroy', $subject) }}" onsubmit="return confirm('Delete this subject? Subjects used in an examination cannot be removed.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="Delete"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No subjects match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $subjects->links() }}
    </div>
@endsection
