@extends('layouts.app')

@section('title', 'My Teaching')

@section('content')
    <x-page-header eyebrow="Teacher" title="My Teaching"
        :subtitle="$session ? $session->name.' session · only your assigned classes and subjects are shown.' : 'No academic session is configured.'">
        <x-slot:actions>
            @can('routines.view')
                <x-button :href="route('teacher.schedule')" variant="secondary" icon="calendar">My class schedule</x-button>
            @endcan
            <x-button :href="route('exams.marks-entry')" variant="primary" icon="edit-3">Marks entry</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $staff)
        <div class="card">
            <div class="card-body">
                <div class="warning-box">
                    Your user account is not linked to a staff record. Ask an administrator to link you to a teacher profile before entering marks.
                </div>
            </div>
        </div>
    @else
        <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
            <x-stat-card label="My assignments" :value="number_format($assignments->count())" icon="book-open" tone="student" meta="Active this session" />
            <x-stat-card label="My classes" :value="number_format($assignments->pluck('class_id')->unique()->count())" icon="graduation-cap" tone="collection" meta="Distinct classes" />
            <x-stat-card label="My subjects" :value="number_format($assignments->pluck('subject_id')->unique()->count())" icon="book" tone="expense" meta="Distinct subjects" />
            <x-stat-card label="My students" :value="number_format($students->count())" icon="users" tone="pending" meta="In assigned sections" />
        </div>

        <div class="grid-side-main">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">My Classes & Subjects</h3><p class="card-sub">{{ $assignments->count() }} active assignment(s)</p></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Class</th><th>Section</th><th>Subject</th><th>Effective</th><th class="actions"></th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td class="cell-title">{{ $assignment->class?->name }}</td>
                                    <td>{{ $assignment->section?->name ?? 'Whole class' }}</td>
                                    <td>{{ $assignment->subject?->name }}</td>
                                    <td class="cell-sub">{{ $assignment->starts_on?->format('d M Y') ?? '—' }} &rarr; {{ $assignment->ends_on?->format('d M Y') ?? 'open' }}</td>
                                    <td class="actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('exams.marks-entry', ['examination_subject_id' => optional($examSubjects->first(fn ($paper) => $paper->class_id === $assignment->class_id && $paper->subject_id === $assignment->subject_id))->id, 'section_id' => $assignment->section_id]) }}">Marks</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">You have no active assignments for this session.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">My Examination Schedule</h3><p class="card-sub">Papers for your assigned subjects</p></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Exam</th><th>Class</th><th>Subject</th><th>Date</th><th class="actions"></th></tr></thead>
                        <tbody>
                            @forelse ($examSubjects as $paper)
                                <tr>
                                    <td>{{ $paper->examination?->name }}</td>
                                    <td>{{ $paper->class?->name }}</td>
                                    <td>{{ $paper->subject?->name }}</td>
                                    <td class="cell-sub">{{ $paper->exam_date?->format('d M Y') ?? 'Not scheduled' }}</td>
                                    <td class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('exams.marks-entry', ['exam_id' => $paper->examination_id, 'examination_subject_id' => $paper->id]) }}">Enter marks</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">No examination papers have been created for your assignment yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">My Students</h3><p class="card-sub">{{ $students->count() }} student(s) in your assigned classes and sections</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Admission no</th><th>Class</th><th>Section</th><th>Roll</th></tr></thead>
                    <tbody>
                        @forelse ($students as $enrollment)
                            <tr>
                                <td class="cell-title">{{ $enrollment->student?->full_name }}</td>
                                <td class="mono">{{ $enrollment->student?->admission_no }}</td>
                                <td>{{ $enrollment->class?->name }}</td>
                                <td>{{ $enrollment->section?->name }}</td>
                                <td>{{ $enrollment->roll_no }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">No students are enrolled in your assigned classes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
