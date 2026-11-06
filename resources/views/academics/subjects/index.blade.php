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
            @can('academics.manage')
                <form id="bulk-delete-subjects" method="POST" action="{{ route('subjects.bulk-destroy') }}" onsubmit="return confirm('Delete selected subjects that are not used in examinations? Their class-subject mappings will also be removed.')">
                    @csrf
                    @method('DELETE')
                    <button id="bulk-delete-subjects-button" type="submit" class="btn btn-secondary btn-sm" disabled>Delete selected (0)</button>
                </form>
            @endcan
            <form method="GET" action="{{ route('subjects.index') }}" class="filter-bar" style="margin:0">
                <div class="field grow" style="min-width:220px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name or code"></div>
                <x-button type="submit" variant="secondary" icon="filter">Search</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr>@can('academics.manage')<th style="width:42px"><input type="checkbox" aria-label="Select all deletable subjects on this page" onchange="document.querySelectorAll('.subject-bulk-check:not(:disabled)').forEach(box => box.checked = this.checked); updateSubjectBulkDelete()"></th>@endcan<th>Code</th><th>Subject</th><th>Type</th><th class="num">Full marks</th><th class="num">Pass marks</th><th class="num">Class maps</th><th class="num">Exam uses</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                                @can('academics.manage')<td><input class="subject-bulk-check" type="checkbox" name="subject_ids[]" form="bulk-delete-subjects" value="{{ $subject->id }}" @disabled($subject->examination_subjects_count > 0) aria-label="Select {{ $subject->name }}" onchange="updateSubjectBulkDelete()" title="{{ $subject->examination_subjects_count > 0 ? 'Used in an examination and cannot be deleted' : 'Select subject' }}"></td>@endcan
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
                        <tr><td colspan="{{ auth()->user()?->can('academics.manage') ? 9 : 8 }}" class="table-empty">No subjects match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $subjects->links() }}
    </div>

    <script>
        function updateSubjectBulkDelete() {
            const boxes = Array.from(document.querySelectorAll('.subject-bulk-check:not(:disabled)'));
            const selected = boxes.filter(box => box.checked).length;
            const button = document.getElementById('bulk-delete-subjects-button');
            const master = document.querySelector('thead input[type="checkbox"][aria-label="Select all deletable subjects on this page"]');

            if (button) {
                button.disabled = selected === 0;
                button.textContent = `Delete selected (${selected})`;
            }
            if (master) {
                master.checked = boxes.length > 0 && selected === boxes.length;
                master.indeterminate = selected > 0 && selected < boxes.length;
            }
        }
    </script>
@endsection
