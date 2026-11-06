@extends('layouts.print')

@section('title', 'Attendance Report')

@php
    $titles = ['class' => 'Class-wise Attendance Summary', 'monthly' => 'Monthly Attendance Report', 'student' => 'Student-wise Attendance Report'];
    $attendanceRate = $summary['records'] > 0 ? round(($summary['present'] / $summary['records']) * 100, 1) : 0;
    $selectedStudent = $studentOptions->firstWhere('id', $studentId);
@endphp

@push('head')
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm 22mm; }
        .attendance-print-sheet { max-width: 1000px; margin: 0 auto; padding: 26px 30px; background: #fff; color: #18243a; font: 12px/1.45 Arial, sans-serif; }
        .attendance-print-brand { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; padding-bottom:18px; border-bottom:2px solid #334e75; }
        .attendance-print-school { margin:0; color:#19355c; font-size:24px; font-weight:750; letter-spacing:.01em; }
        .attendance-print-school-meta { margin-top:5px; color:#617087; font-size:10px; }
        .attendance-print-document-meta { text-align:right; color:#53647c; font-size:10px; }
        .attendance-print-document-meta strong { display:block; margin-bottom:4px; color:#203b62; font-size:12px; }
        .attendance-print-title { margin:22px 0 4px; color:#18365f; font-size:20px; font-weight:700; }
        .attendance-print-subtitle { margin:0; color:#64738a; font-size:11px; }
        .attendance-print-filters { display:flex; flex-wrap:wrap; gap:8px; margin:16px 0; }
        .attendance-print-chip { padding:6px 10px; border:1px solid #d7dfeb; border-radius:999px; background:#f6f8fb; color:#394c68; font-size:10px; }
        .attendance-print-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin:16px 0 20px; }
        .attendance-print-stat { padding:11px 13px; border:1px solid #dde4ee; border-radius:8px; background:#f8fafc; }
        .attendance-print-stat-label { color:#68778d; font-size:9px; text-transform:uppercase; letter-spacing:.07em; }
        .attendance-print-stat-value { margin-top:3px; color:#1e3a63; font-size:20px; font-weight:700; }
        .attendance-print-section-title { margin:20px 0 8px; color:#243f67; font-size:13px; font-weight:700; }
        .attendance-print-table { width:100%; border-collapse:collapse; font-size:10px; }
        .attendance-print-table th { padding:8px 7px; border:1px solid #cbd5e2; background:#eaf0f7; color:#203a60; text-align:left; font-weight:700; }
        .attendance-print-table td { padding:7px; border:1px solid #d8e0ea; color:#26364d; vertical-align:top; }
        .attendance-print-table tbody tr:nth-child(even) { background:#f8fafc; }
        .attendance-print-table .num { text-align:right; font-variant-numeric:tabular-nums; }
        .attendance-print-table .rate { color:#1c6a4c; font-weight:700; }
        .attendance-print-empty { padding:28px; border:1px dashed #cbd5e2; color:#66768c; text-align:center; }
        .attendance-print-note { margin-top:14px; color:#68778d; font-size:9px; }
        @media print {
            .attendance-print-sheet { max-width:none; padding:0; }
            .attendance-print-table thead { display:table-header-group; }
            .attendance-print-table tr { break-inside:avoid; }
            .attendance-print-stat, .attendance-print-chip, .attendance-print-table th { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
        }
    </style>
@endpush

@section('content')
    <article class="attendance-print-sheet">
        <header class="attendance-print-brand">
            <div>
                <h1 class="attendance-print-school">{{ $schoolSettings['name'] ?? config('app.name') }}</h1>
                @if (filled($schoolSettings['address'] ?? null))<div class="attendance-print-school-meta">{{ $schoolSettings['address'] }}</div>@endif
                <div class="attendance-print-school-meta">
                    @if (filled($schoolSettings['phone'] ?? null)){{ $schoolSettings['phone'] }}@endif
                    @if (filled($schoolSettings['phone'] ?? null) && filled($schoolSettings['email'] ?? null)) · @endif
                    @if (filled($schoolSettings['email'] ?? null)){{ $schoolSettings['email'] }}@endif
                </div>
            </div>
            <div class="attendance-print-document-meta">
                <strong>{{ $session?->name ?? 'Academic Session' }} Session</strong>
                Generated {{ now()->format('d M Y, h:i A') }}
            </div>
        </header>

        <h2 class="attendance-print-title">{{ $titles[$printType] }}</h2>
        <p class="attendance-print-subtitle">Attendance records from {{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</p>

        <div class="attendance-print-filters">
            @if ($classId)<span class="attendance-print-chip">Class: {{ $classes->firstWhere('id', $classId)?->name ?? 'Selected class' }}</span>@else<span class="attendance-print-chip">All accessible classes</span>@endif
            @if ($sectionId)<span class="attendance-print-chip">Section: {{ $sections->firstWhere('id', $sectionId)?->name ?? 'Selected section' }}</span>@endif
            @if ($selectedStudent)<span class="attendance-print-chip">Student: {{ $selectedStudent->full_name }} (Student ID: {{ $selectedStudent->student_code }})</span>@endif
            @if ($month)<span class="attendance-print-chip">Month: {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }}</span>@endif
        </div>

        @if ($searchMessage)
            <div class="attendance-print-empty">{{ $searchMessage }}</div>
        @endif

        <section class="attendance-print-stats" aria-label="Attendance totals">
            <div class="attendance-print-stat"><div class="attendance-print-stat-label">{{ $selectedStudent ? 'Student' : 'Students' }}</div><div class="attendance-print-stat-value">{{ $selectedStudent?->full_name ?? number_format($summary['students']) }}</div></div>
            <div class="attendance-print-stat"><div class="attendance-print-stat-label">Attendance records</div><div class="attendance-print-stat-value">{{ number_format($summary['records']) }}</div></div>
            <div class="attendance-print-stat"><div class="attendance-print-stat-label">Present + late</div><div class="attendance-print-stat-value">{{ number_format($summary['present']) }}</div></div>
            <div class="attendance-print-stat"><div class="attendance-print-stat-label">Attendance rate</div><div class="attendance-print-stat-value">{{ $attendanceRate }}%</div></div>
        </section>

        @if ($printType === 'class')
            <h3 class="attendance-print-section-title">Class and section summary</h3>
            <table class="attendance-print-table">
                <thead><tr><th>Class</th><th>Section</th><th class="num">Students</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Excused</th><th class="num">Rate</th></tr></thead>
                <tbody>
                    @forelse ($classRows as $row)
                        <tr><td>{{ $row['class'] }}</td><td>{{ $row['section'] }}</td><td class="num">{{ $row['students'] }}</td><td class="num">{{ $row['present'] }}</td><td class="num">{{ $row['late'] }}</td><td class="num">{{ $row['absent'] }}</td><td class="num">{{ $row['excused'] }}</td><td class="num rate">{{ $row['percentage'] }}%</td></tr>
                    @empty
                        <tr><td colspan="8" class="attendance-print-empty">No class attendance records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($printType === 'monthly')
            <h3 class="attendance-print-section-title">Monthly totals</h3>
            <table class="attendance-print-table">
                <thead><tr><th>Month</th><th class="num">Records</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Excused</th><th class="num">Rate</th></tr></thead>
                <tbody>
                    @forelse ($monthlyRows as $row)
                        <tr><td>{{ $row['month'] }}</td><td class="num">{{ $row['total'] }}</td><td class="num">{{ $row['present'] }}</td><td class="num">{{ $row['late'] }}</td><td class="num">{{ $row['absent'] }}</td><td class="num">{{ $row['excused'] }}</td><td class="num rate">{{ $row['percentage'] }}%</td></tr>
                    @empty
                        <tr><td colspan="7" class="attendance-print-empty">No monthly attendance records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($printType === 'student' && $studentId)
            <h3 class="attendance-print-section-title">Attendance details for {{ $selectedStudent?->full_name ?? 'selected student' }} @if ($selectedStudent) · Student ID {{ $selectedStudent->student_code }}@endif</h3>
            <table class="attendance-print-table">
                <thead><tr><th>Date</th><th>Roll no.</th><th>Class · Section</th><th>Status</th><th>Note</th></tr></thead>
                <tbody>
                    @forelse ($studentDailyRows as $row)
                        <tr><td>{{ $row['date']->format('d M Y') }}</td><td>{{ $row['roll_no'] ?? '—' }}</td><td>{{ $row['class'] }} · {{ $row['section'] }}</td><td>{{ ucfirst($row['status']) }}</td><td>{{ $row['note'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="attendance-print-empty">No daily attendance records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <h3 class="attendance-print-section-title">Student attendance summary</h3>
            <table class="attendance-print-table">
                <thead><tr><th>Student</th><th>Student ID</th><th>Class · Section</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Excused</th><th class="num">Rate</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr><td>{{ $row['student'] }}</td><td>{{ $row['student_id'] ?? $row['admission_no'] }}</td><td>{{ $row['class'] }} · {{ $row['section'] }}</td><td class="num">{{ $row['present'] }}</td><td class="num">{{ $row['late'] }}</td><td class="num">{{ $row['absent'] }}</td><td class="num">{{ $row['excused'] }}</td><td class="num rate">{{ $row['percentage'] }}%</td></tr>
                    @empty
                        <tr><td colspan="8" class="attendance-print-empty">No student attendance records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if (! $studentId)<p class="attendance-print-note">Enter one student's name, roll number or Student ID in the report filters to print a date-by-date attendance history.</p>@endif
        @endif

        <p class="attendance-print-note">Attendance rate counts Present and Late as attended. Excused and Absent are reported separately.</p>
    </article>
@endsection
