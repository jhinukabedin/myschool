@extends('layouts.app')

@section('title', 'Passed Out Archive')

@section('content')
    <x-page-header eyebrow="Students" title="Passed Out Archive" subtitle="Former students retained for transcripts, verification and financial history.">
        <x-slot:actions>
            <x-button :href="route('students.index')" variant="secondary" icon="arrow-left">Active students</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Archived Records</h3><p class="card-sub">Passed out, transferred and archived students</p></div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field" style="min-width:220px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search archived students"></div>
                <x-button type="submit" variant="secondary" icon="search">Search</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Student</th><th>Student ID</th><th>Last class</th><th>Session</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                    @forelse ($students as $student)
                        @php($enrollment = $student->enrollments->sortByDesc('id')->first())
                        <tr>
                            <td>
                                <div class="cell-main">
                                    <span class="table-avatar">{{ Str::substr($student->full_name, 0, 1) }}</span>
                                    <div><div class="cell-title">{{ $student->full_name }}</div><div class="cell-sub">{{ $student->primaryGuardian()?->phone }}</div></div>
                                </div>
                            </td>
                            <td class="mono" style="font-size:11px">{{ $student->student_code }}</td>
                            <td>{{ $enrollment?->class?->name ?? '—' }}</td>
                            <td>{{ $enrollment?->academicSession?->name ?? '—' }}</td>
                            <td><x-badge :color="$student->status_color">{{ $student->status_label }}</x-badge></td>
                            <td class="muted">{{ $student->updated_at?->format('d M Y') }}</td>
                            <td class="actions"><a href="{{ route('students.show', $student) }}" class="btn-icon" title="View record"><x-icon name="eye" :size="15" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">No archived student records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
@endsection
