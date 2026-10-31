@extends('layouts.app')

@section('title', 'Attendance Report')

@section('content')
    <x-page-header eyebrow="Academics" title="Attendance Report" :subtitle="$session ? 'Attendance rates for '.$session->name.' between '.$from.' and '.$to.'.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('attendance.index')" variant="secondary" icon="calendar-check">Mark attendance</x-button>
            @can('reports.export')
                <x-button :href="route('attendance.report.export', request()->query())" variant="primary" icon="download">Export CSV</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Students" :value="number_format($summary['students'])" icon="users" tone="student" meta="With records" />
        <x-stat-card label="Records" :value="number_format($summary['records'])" icon="clipboard-list" tone="collection" meta="Attendance entries" />
        <x-stat-card label="Present / late" :value="number_format($summary['present'])" icon="user-check" tone="collection" meta="Attended" />
        <x-stat-card label="Absent" :value="number_format($summary['absent'])" icon="user" tone="pending" meta="Unattended" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Filters</h3><p class="card-sub">Adjust the period, class or section</p></div>
            <form method="GET" action="{{ route('attendance.report') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field"><label class="field-label" for="from">From</label><input class="input" id="from" type="date" name="from" value="{{ $from }}"></div>
                <div class="field"><label class="field-label" for="to">To</label><input class="input" id="to" type="date" name="to" value="{{ $to }}"></div>
                <div class="field">
                    <label class="field-label" for="class_id">Class</label>
                    <select class="select" id="class_id" name="class_id">
                        <option value="">All classes</option>
                        @foreach ($classes as $classOption)
                            <option value="{{ $classOption->id }}" @selected((string) $classId === (string) $classOption->id)>{{ $classOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="section_id">Section</label>
                    <select class="select" id="section_id" name="section_id">
                        <option value="">All sections</option>
                        @foreach ($sections as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected((string) $sectionId === (string) $sectionOption->id)>{{ $sectionOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field grow" style="min-width:190px"><label class="field-label" for="q">Student</label><input class="input" id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name, admission no"></div>
                <x-button type="submit" variant="secondary" icon="filter">Apply</x-button>
            </form>
        </div>
    </div>

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Student Attendance</h3><p class="card-sub">{{ $rows->count() }} student(s) in this period</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Class</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Excused</th><th class="num">Total</th><th class="num">Rate</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <div class="cell-title">{{ $row['student'] }}</div>
                                    <div class="cell-sub">{{ $row['admission_no'] }}</div>
                                </td>
                                <td>{{ $row['class'] }} · {{ $row['section'] }}</td>
                                <td class="num text-success">{{ $row['present'] }}</td>
                                <td class="num text-warning">{{ $row['late'] }}</td>
                                <td class="num text-danger">{{ $row['absent'] }}</td>
                                <td class="num">{{ $row['excused'] }}</td>
                                <td class="num">{{ $row['total'] }}</td>
                                <td class="num">
                                    <x-badge :color="$row['percentage'] >= 80 ? 'success' : ($row['percentage'] >= 60 ? 'warning' : 'danger')">{{ $row['percentage'] }}%</x-badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="table-empty">No attendance records match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">By Class</h3><p class="card-sub">Aggregated status counts</p></div></div>
            <div class="table-wrap">
                <table class="data-table" style="white-space:normal">
                    <thead><tr><th>Class</th><th class="num">Present</th><th class="num">Absent</th><th class="num">Late</th><th class="num">Excused</th></tr></thead>
                    <tbody>
                        @forelse ($byClass as $row)
                            <tr>
                                <td class="cell-title">{{ $row['class'] }}</td>
                                <td class="num text-success">{{ $row['present'] }}</td>
                                <td class="num text-danger">{{ $row['absent'] }}</td>
                                <td class="num text-warning">{{ $row['late'] }}</td>
                                <td class="num">{{ $row['excused'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">No class totals for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
