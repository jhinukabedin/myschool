@extends('layouts.app')

@section('title', 'Attendance')

@php
    $selectedClassId = old('class_id', $class?->id);
    $selectedSectionId = old('section_id', $section?->id);
    $summaryMap = collect($summary ?? []);
    $markedStudentCount = $students->filter(fn ($enrollment) => $records->has($enrollment->student_id))->count();
@endphp

@section('content')
    <x-page-header eyebrow="Academics" title="Daily Attendance" :subtitle="$session ? 'Mark and update the class register for '.$session->name.'.' : 'No academic session is configured.'">
        <x-slot:actions>
            @can('attendance.report')
                <x-button :href="route('attendance.report')" variant="secondary" icon="clipboard-list">Attendance report</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Present" :value="number_format((int) ($summaryMap['present'] ?? 0))" icon="user-check" tone="collection" :meta="$date" />
        <x-stat-card label="Absent" :value="number_format((int) ($summaryMap['absent'] ?? 0))" icon="user" tone="pending" meta="Marked absent" />
        <x-stat-card label="Late" :value="number_format((int) ($summaryMap['late'] ?? 0))" icon="clock" tone="expense" meta="Arrived late" />
        <x-stat-card label="Excused" :value="number_format((int) ($summaryMap['excused'] ?? 0))" icon="shield" tone="student" meta="Approved absence" />
    </div>

    @if ($holiday)
        <div class="alert alert-info" role="status" style="display:flex;gap:10px;align-items:flex-start">
            <span class="alert-icon"><x-icon name="calendar" :size="17" /></span>
            <div>
                <strong>{{ __('Holiday') }}: {{ $holiday['name'] }}</strong>
                <div style="font-size:12px;margin-top:2px">{{ __('No attendance is expected on this date. Choose another date or update the academic calendar.') }}</div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Register</h3><p class="card-sub">Choose a date, class and section to load the roster</p></div>
            <form method="GET" action="{{ route('attendance.index') }}" class="filter-bar" style="margin:0">
                <div class="field"><label class="field-label" for="date">Date</label><input class="input" id="date" type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}"></div>
                <div class="field">
                    <label class="field-label" for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id" onchange="document.getElementById('section_id').value=''; this.form.requestSubmit()">
                        <option value="">Select class</option>
                        @foreach ($classes as $classOption)
                            <option value="{{ $classOption->id }}" @selected((string) $selectedClassId === (string) $classOption->id)>{{ $classOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="section_id">Section</label>
                    <select class="select" id="section_id" name="section_id" @disabled(! $class)>
                        <option value="">{{ $class ? 'Select section' : 'Choose a class first' }}</option>
                        @foreach ($sections as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected((string) $selectedSectionId === (string) $sectionOption->id)>{{ $sectionOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Load register</x-button>
            </form>
        </div>
    </div>

    @if (! $class || ! $section)
        <div class="card">
            <x-empty icon="calendar-check" title="Select a class and section" message="The daily register appears once a class and section are selected." />
        </div>
    @elseif ($students->isEmpty())
        <div class="card">
            <x-empty icon="users" title="No active students" message="There are no active enrolments for this class and section in the current session." />
        </div>
    @else
        <form method="POST" action="{{ route('attendance.store') }}" x-data="{ studentSearch: '' }">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="class_id" value="{{ $class->id }}">
            <input type="hidden" name="section_id" value="{{ $section->id }}">

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ $class->name }} · Section {{ $section->name }}</h3>
                        <p class="card-sub">{{ $students->count() }} students · {{ $markedStudentCount }} marked · {{ \Illuminate\Support\Carbon::parse($date)->format('l, d M Y') }}</p>
                    </div>
                    <div class="field" style="margin-left:auto;min-width:190px;max-width:260px">
                        <label class="field-label" for="student-search">Find a student</label>
                        <input class="input" id="student-search" type="search" x-model="studentSearch" placeholder="Name or admission no">
                    </div>
                    @can('attendance.manage')
                        <div class="page-actions">
                            <button type="button" class="btn btn-secondary btn-sm" @click="document.querySelectorAll('input[type=radio][value=present]').forEach(el => el.checked = true)">All present</button>
                            <button type="button" class="btn btn-secondary btn-sm" @click="document.querySelectorAll('input[type=radio][value=absent]').forEach(el => el.checked = true)">All absent</button>
                        </div>
                    @endcan
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>Roll</th><th>Student</th><th>Status</th><th style="min-width:200px">Note</th><th>Saved</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $enrollment)
                                @php
                                    $record = $records->get($enrollment->student_id);
                                    $current = old('statuses.'.$enrollment->student_id, $record?->status);
                                    $note = old('notes.'.$enrollment->student_id, $record?->note);
                                @endphp
                                <tr data-search="{{ Str::lower(($enrollment->student?->full_name ?? '').' '.($enrollment->student?->student_code ?? '')) }}" x-show="!studentSearch || $el.dataset.search.includes(studentSearch.toLowerCase())">
                                    <td class="muted">{{ $enrollment->roll_no }}</td>
                                    <td>
                                        <div class="cell-main">
                                            <span class="table-avatar">{{ Str::substr($enrollment->student?->full_name ?? '?', 0, 1) }}</span>
                                            <div>
                                                <div class="cell-title">{{ $enrollment->student?->full_name }}</div>
                                                <div class="cell-sub">Student ID: {{ $enrollment->student?->student_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="attendance-toggle">
                                            @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
                                                <label>
                                                    <input type="radio" name="statuses[{{ $enrollment->student_id }}]" value="{{ $value }}" @checked($current === $value) @disabled(! auth()->user()->can('attendance.manage'))>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('statuses.'.$enrollment->student_id)
                                            <span class="field-error">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td>
                                        <input class="input" type="text" name="notes[{{ $enrollment->student_id }}]" value="{{ $note }}" placeholder="Optional note" maxlength="255" @disabled(! auth()->user()->can('attendance.manage'))>
                                        @error('notes.'.$enrollment->student_id)
                                            <span class="field-error">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td>
                                        @if ($record)
                                            <x-badge color="success">Saved</x-badge>
                                            <div class="cell-sub">{{ $record->markedBy?->name ?? 'Account unavailable' }}</div>
                                        @else
                                            <x-badge color="muted">New</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @can('attendance.manage')
                    <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <span class="muted">
                            @if ($holiday)
                                {{ __('Attendance is disabled on this holiday.') }}
                            @else
                                New students stay unmarked until selected. Existing records update in place and show who saved them.
                            @endif
                        </span>
                        <x-button type="submit" variant="primary" icon="save" :disabled="$holiday !== null">{{ __('Save attendance') }}</x-button>
                    </div>
                @endcan
            </div>
        </form>
    @endif
@endsection
