@extends('layouts.app')

@section('title', 'Student Promotion')

@section('content')
    <x-page-header eyebrow="Students" title="Promotion" subtitle="Move an active class into the next academic session without losing historical enrolments.">
        <x-slot:actions>
            <x-button :href="route('students.index')" variant="secondary" icon="users">All students</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header"><div><h3 class="card-title">Select Source Class</h3><p class="card-sub">Preview the students that will be promoted</p></div></div>
        <form method="GET" action="{{ route('promotion.index') }}">
            <div class="form-section">
                <div class="form-grid three">
                    <x-select name="from_session_id" label="From session" :options="$sessions->pluck('name', 'id')" :selected="$fromSession?->id" required />
                    <x-select name="from_class_id" label="From class" :options="$classes->pluck('name', 'id')" :selected="$fromClass?->id" required />
                    <div style="display:flex;align-items:flex-end"><x-button type="submit" variant="secondary" icon="filter" class="btn-block">Load students</x-button></div>
                </div>
            </div>
        </form>
    </div>

    @if ($fromSession && $fromClass)
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Promote {{ $fromClass->name }} Students</h3><p class="card-sub">{{ $preview->count() }} active student(s) in {{ $fromSession->name }}</p></div>
                <x-badge color="violet">{{ $preview->count() }} selected by default</x-badge>
            </div>
            <form method="POST" action="{{ route('promotion.store') }}" onsubmit="return confirm('Promote the selected students? This creates new enrolments and preserves the old records.')">
                @csrf
                <input type="hidden" name="from_session_id" value="{{ $fromSession->id }}">
                <input type="hidden" name="from_class_id" value="{{ $fromClass->id }}">

                <div class="form-section">
                    <div class="form-section-title">Destination</div>
                    <div class="form-grid three">
                        <x-select name="to_session_id" label="To session" :options="$sessions->pluck('name', 'id')" placeholder="Choose session" required />
                        <x-select name="to_class_id" label="To class" :options="$classes->pluck('name', 'id')" required />
                        <x-select name="to_section_id" label="To section" :options="$sections->pluck('name', 'id')" required />
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th style="width:44px"><input type="checkbox" checked onchange="document.querySelectorAll('.promote-check').forEach(c => c.checked = this.checked)"></th><th>Student</th><th>Current section</th><th>Roll</th><th>Guardian phone</th></tr></thead>
                        <tbody>
                            @forelse ($preview as $enrollment)
                                <tr>
                                    <td><input class="promote-check" type="checkbox" name="only_student_ids[]" value="{{ $enrollment->student_id }}" checked></td>
                                    <td>
                                        <div class="cell-title">{{ $enrollment->student?->full_name }}</div>
                                        <div class="cell-sub">Student ID: {{ $enrollment->student?->student_code }}</div>
                                    </td>
                                    <td>{{ $enrollment->section?->name }}</td>
                                    <td>{{ $enrollment->roll_no }}</td>
                                    <td class="muted">{{ $enrollment->student?->primaryGuardian()?->phone ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">No active students found in this class and session.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="form-section" style="display:flex;justify-content:flex-end;gap:8px">
                    <x-button :href="route('promotion.index')" variant="ghost">Reset</x-button>
                    <x-button type="submit" variant="primary" icon="trending-up" :disabled="$preview->isEmpty()">Promote selected students</x-button>
                </div>
            </form>
        </div>
    @endif
@endsection
