@extends('layouts.print')

@section('title', 'Result Sheet · '.$student->full_name)

@section('content')
    <div class="print-hide" style="max-width:900px;margin:0 auto 12px;display:flex;justify-content:space-between;align-items:center;gap:8px">
        <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm">Back to results</a>
        <span class="muted-2" style="font-size:11px">Published result · use your browser print dialog to save as PDF.</span>
    </div>

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
                <div class="receipt-sub">{{ $exam->academicSession?->name }} · {{ ucfirst($exam->exam_type) }}</div>
            </div>
        </div>

        <div class="receipt-grid">
            <div><div class="receipt-label">Student</div><div class="receipt-value">{{ $student->full_name }}</div></div>
            <div><div class="receipt-label">Admission No</div><div class="receipt-value">{{ $student->admission_no }}</div></div>
            <div><div class="receipt-label">Class</div><div class="receipt-value">{{ $enrollment->class?->name }}</div></div>
            <div><div class="receipt-label">Section / Roll</div><div class="receipt-value">{{ $enrollment->section?->name ?? '—' }} / {{ $enrollment->roll_no ?? '—' }}</div></div>
            <div><div class="receipt-label">Published</div><div class="receipt-value">{{ $published_at?->format('d M Y, h:i A') }}</div></div>
            <div><div class="receipt-label">Grading scheme</div><div class="receipt-value">{{ $exam->gradingScheme?->name ?? 'Institution default' }}</div></div>
        </div>

        <table class="receipt-table" style="width:100%;margin-top:18px">
            <thead>
                <tr>
                    <th style="text-align:left">Subject</th>
                    <th>Full marks</th>
                    <th>Pass marks</th>
                    <th>Obtained</th>
                    <th>Grade</th>
                    <th>Grade point</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($result['subjects'] as $subjectRow)
                    <tr>
                        <td style="text-align:left">
                            {{ $subjectRow['subject']?->name ?? 'Subject' }}
                            @if (! empty($subjectRow['components']))
                                <div class="muted-2" style="font-size:10px;margin-top:2px">
                                    {{ collect($subjectRow['components'])->map(fn ($component) => $component['name'].': '.($component['obtained'] === null ? '—' : number_format((float) $component['obtained'], 2)).'/'.number_format((float) $component['full_marks'], 0))->implode(' · ') }}
                                </div>
                            @endif
                        </td>
                        <td>{{ number_format($subjectRow['full_marks'], 0) }}</td>
                        <td>{{ number_format($subjectRow['pass_marks'], 0) }}</td>
                        <td>
                            @if ($subjectRow['special_status'] === 'missing')
                                Not entered
                            @elseif ($subjectRow['exempt'])
                                Exempt
                            @elseif ($subjectRow['absent'])
                                Absent
                            @else
                                {{ number_format((float) $subjectRow['obtained'], 2) }}
                            @endif
                        </td>
                        <td>{{ $subjectRow['grade'] }}</td>
                        <td>{{ $subjectRow['grade_point'] === null ? '—' : number_format((float) $subjectRow['grade_point'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-grid" style="margin-top:18px">
            <div><div class="receipt-label">Total marks</div><div class="receipt-value">{{ number_format($result['total'], 2) }} / {{ number_format($result['full_total'], 2) }}</div></div>
            <div><div class="receipt-label">Percentage</div><div class="receipt-value">{{ number_format($result['percentage'], 2) }}%</div></div>
            <div><div class="receipt-label">GPA</div><div class="receipt-value">{{ number_format($result['gpa'], 2) }}</div></div>
            <div><div class="receipt-label">Grade</div><div class="receipt-value">{{ $result['grade'] }}</div></div>
            <div><div class="receipt-label">Result</div><div class="receipt-value">{{ $result['result'] }}</div></div>
            <div><div class="receipt-label">Failed subjects</div><div class="receipt-value">{{ $result['failed_subjects'] }}</div></div>
        </div>

        @if (! empty($result['special_statuses']))
            <p class="muted-2" style="margin-top:12px;font-size:12px">
                Special status: {{ collect($result['special_statuses'])->map(fn ($status) => ucfirst($status))->implode(', ') }}.
                Contact the examination office for clarification.
            </p>
        @endif

        <div class="receipt-foot" style="margin-top:36px">
            <div>Class Teacher</div>
            <div>Controller of Examinations</div>
            <div>Principal</div>
        </div>
        <p class="muted-2" style="text-align:center;font-size:11px;margin-top:18px">
            This is a computer-generated result sheet. {{ $portalRole ?? 'Portal' }} access confirmed by the system audit log.
        </p>
    </div>
@endsection
