@extends('layouts.app')

@section('title', 'All Students')

@section('content')
    <x-page-header eyebrow="Students" title="All Students" :subtitle="$session ? 'Enrolments for the '.$session->name.' session.' : 'No academic session selected.'">
        <x-slot:actions>
            @can('students.create')
                <x-button :href="route('admissions.create')" variant="primary" icon="user-plus">New admission</x-button>
            @endcan
            @can('reports.export')
                <x-button :href="route('session-report.export')" variant="secondary" icon="download">Export session</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card label="Active students" :value="number_format($summary['active'])" icon="users" tone="student" meta="All sessions" />
        <x-stat-card label="New this session" :value="number_format($summary['new'])" icon="user-plus" tone="collection" :meta="$session?->name ?? 'No session'" />
        <x-stat-card label="Archive" :value="number_format($summary['archived'])" icon="archive" tone="expense" meta="Passed out, transferred, archived" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Student Directory</h3><p class="card-sub">{{ $students->total() }} record(s) found</p></div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field grow" style="min-width:190px">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, student ID, admission no, phone">
                </div>
                <div class="field">
                    <select class="select" name="class_id">
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">Any status</option>
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
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
                    <tr><th>Student</th><th>Student ID / Admission</th><th>Class</th><th>Guardian</th><th>Status</th><th>Admitted</th><th class="actions">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        @php($enrollment = $student->enrollments->first())
                        <tr>
                            <td>
                                <div class="cell-main">
                                    @if ($student->photo_url)
                                        <img src="{{ $student->photo_url }}" alt="" class="table-avatar" style="object-fit:cover">
                                    @else
                                        <span class="table-avatar">{{ Str::substr($student->full_name, 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <div class="cell-title"><a href="{{ route('students.show', $student) }}">{{ $student->full_name }}</a></div>
                                        <div class="cell-sub">{{ ucfirst($student->gender) }} · {{ $student->date_of_birth?->age }} yrs</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="mono" style="font-size:11px">{{ $student->student_code ?? '—' }}</div>
                                <div class="cell-sub mono">{{ $student->admission_no }}</div>
                            </td>
                            <td>
                                @if ($enrollment)
                                    <div class="cell-title">{{ $enrollment->class?->name }}</div>
                                    <div class="cell-sub">Section {{ $enrollment->section?->name }} · Roll {{ $enrollment->roll_no }}</div>
                                @else
                                    <span class="muted">Not enrolled</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ $student->primaryGuardian()?->name ?? '—' }}</div>
                                <div class="cell-sub">{{ $student->primaryGuardian()?->phone }}</div>
                            </td>
                            <td><x-badge :color="$student->status_color">{{ $student->status_label }}</x-badge></td>
                            <td class="muted">{{ $student->admission_date?->format('d M Y') }}</td>
                            <td class="actions">
                                <a href="{{ route('students.show', $student) }}" class="btn-icon" title="View"><x-icon name="eye" :size="15" /></a>
                                @can('fees.collect')
                                    <a href="{{ route('fees.collection.show', $student) }}" class="btn-icon" title="Collect fees"><x-icon name="wallet" :size="15" /></a>
                                @endcan
                                @can('students.update')
                                    <a href="{{ route('students.edit', $student) }}" class="btn-icon" title="Edit"><x-icon name="edit" :size="15" /></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No students match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
@endsection
