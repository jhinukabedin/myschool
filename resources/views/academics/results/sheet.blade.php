@extends('layouts.print')

@section('title', 'Result Sheet · '.$student->full_name)

@section('content')
    <div class="receipt-sheet" style="max-width:900px">
        <div class="receipt-head">
            <div>
                <div class="receipt-school">{{ school_name() }}</div>
                <div class="receipt-sub">{{ school_setting('school_address') }}</div>
                <div class="receipt-sub">{{ school_setting('school_phone') }} · {{ school_setting('school_email') }}</div>
            </div>
            <div class="receipt-badge">
                <div class="r-title">Academic Result</div>
                <div class="receipt-no">{{ $exam->name }}</div>
                <div class="receipt-sub">{{ $exam->academicSession?->name }}</div>
            </div>
        </div>

        <div class="receipt-grid">
            <div><div class="receipt-label">Student</div><div class="receipt-value">{{ $student->full_name }}</div></div>
            <div><div class="receipt-label">Student ID</div><div class="receipt-value">{{ $student->student_code }}</div></div>
            <div><div class="receipt-label">Class</div><div class="receipt-value">{{ $class->name }} · Section {{ $enrollment->section?->name }}</div></div>
            <div><div class="receipt-label">Roll No</div><div class="receipt-value">{{ $enrollment->roll_no }}</div></div>
            <div><div class="receipt-label">Examination</div><div class="receipt-value">{{ $exam->name }}</div></div>
            <div><div class="receipt-label">Result Date</div><div class="receipt-value">{{ $exam->end_date?->format('d M Y') }}</div></div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr><th>Subject</th><th style="text-align:right">Full Marks</th><th style="text-align:right">Pass Marks</th><th style="text-align:right">Obtained</th><th>Grade</th><th style="text-align:right">Grade Point</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($result['subjects'] as $row)
                    <tr>
                        <td>
                            {{ $row['subject']?->name }}
                            @if (! empty($row['components']))
                                <div style="font-size:10px;color:#6b7280;margin-top:2px">
                                    {{ collect($row['components'])->map(fn ($component) => $component['name'].': '.($component['obtained'] === null ? '—' : number_format((float) $component['obtained'], 2)).'/'.number_format((float) $component['full_marks'], 0))->implode(' · ') }}
                                </div>
                            @endif
                        </td>
                        <td style="text-align:right">{{ number_format($row['full_marks'], 2) }}</td>
                        <td style="text-align:right">{{ number_format($row['pass_marks'], 2) }}</td>
                        <td style="text-align:right">{{ $row['absent'] ? 'Absent' : number_format($row['obtained'], 2) }}</td>
                        <td>{{ $row['grade'] }}</td>
                        <td style="text-align:right">{{ number_format($row['grade_point'], 2) }}</td>
                        <td><span style="color:{{ $row['passed'] ? '#047857' : '#b91c1c' }};font-weight:700">{{ $row['passed'] ? 'Pass' : 'Fail' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-grid" style="padding-top:18px">
            <div><div class="receipt-label">Total Marks</div><div class="receipt-value">{{ number_format($result['total'], 2) }} / {{ number_format($result['full_total'], 2) }}</div></div>
            <div><div class="receipt-label">Percentage</div><div class="receipt-value">{{ number_format($result['percentage'], 2) }}%</div></div>
            <div><div class="receipt-label">GPA</div><div class="receipt-value">{{ number_format($result['gpa'], 2) }}</div></div>
            <div><div class="receipt-label">Grade</div><div class="receipt-value">{{ $result['grade'] }}</div></div>
            <div><div class="receipt-label">Failed Subjects</div><div class="receipt-value">{{ $result['failed_subjects'] }}</div></div>
            <div><div class="receipt-label">Final Result</div><div class="receipt-value" style="color:{{ $result['result'] === 'Pass' ? '#047857' : '#b91c1c' }};font-weight:800">{{ strtoupper($result['result']) }}</div></div>
        </div>

        <div class="receipt-total">Final Result: {{ $result['result'] }} · GPA {{ number_format($result['gpa'], 2) }}</div>

        <div class="receipt-footer">
            <div>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()?->name ?? 'System' }}</div>
            <div class="signature"><div class="signature-line">Class Teacher</div></div>
            <div class="signature"><div class="signature-line">Principal</div></div>
        </div>
    </div>
@endsection
