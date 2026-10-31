@extends('layouts.app')

@section('title', 'Teacher Assignments')

@section('content')
    <x-page-header eyebrow="Academics" title="Teacher Assignments"
        :subtitle="$session ? 'Class, section and subject assignments for the '.$session->name.' session.' : 'No academic session is configured.'">
        <x-slot:actions>
            @can('assignments.manage')
                <x-button :href="route('assignments.create')" variant="primary" icon="plus">New assignment</x-button>
            @endcan
            <x-button :href="route('teacher.dashboard')" variant="secondary" icon="users">My teaching</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Assignments</h3><p class="card-sub">{{ $assignments->total() }} record(s) · deactivate rather than delete</p></div>
            <form method="GET" action="{{ route('assignments.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <select class="select" name="class_id">
                        <option value="">All classes</option>
                        @foreach ($classes as $classOption)
                            <option value="{{ $classOption->id }}" @selected(request('class_id') == $classOption->id)>{{ $classOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="subject_id">
                        <option value="">All subjects</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="staff_id">
                        <option value="">All teachers</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(request('staff_id') == $teacher->id)>{{ $teacher->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">Any status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Session</th><th>Class</th><th>Section</th><th>Subject</th><th>Teacher</th><th>Effective</th><th>Status</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->academicSession?->name }}</td>
                            <td class="cell-title">{{ $assignment->class?->name }}</td>
                            <td>{{ $assignment->section?->name ?? 'Whole class' }}</td>
                            <td>{{ $assignment->subject?->name }}</td>
                            <td>
                                <div class="cell-title">{{ $assignment->staff?->full_name }}</div>
                                <div class="cell-sub">{{ $assignment->staff?->employee_no }}</div>
                            </td>
                            <td class="cell-sub">
                                {{ $assignment->starts_on?->format('d M Y') ?? '—' }}
                                &rarr;
                                {{ $assignment->ends_on?->format('d M Y') ?? 'open' }}
                            </td>
                            <td>
                                <x-badge :color="$assignment->is_active ? 'success' : 'neutral'">{{ $assignment->is_active ? 'Active' : 'Inactive' }}</x-badge>
                            </td>
                            <td class="actions">
                                @can('assignments.manage')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('assignments.edit', $assignment) }}">Edit</a>
                                    <form method="POST" action="{{ route('assignments.toggle', $assignment) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">{{ $assignment->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No teacher assignments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $assignments->links() }}
    </div>
@endsection
