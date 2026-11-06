@extends('layouts.app')

@section('title', 'Attendance Report')

@php
    $selectedMonth = $month ?? '';
    $selectedStudent = $studentOptions->firstWhere('id', $studentId);
@endphp

@section('content')
    <x-page-header eyebrow="Academics" title="Attendance Report" :subtitle="$session ? 'Attendance information for '.$session->name.'.' : 'No academic session is configured.'">
        <x-slot:actions>
            <x-button :href="route('attendance.index')" variant="secondary" icon="calendar-check">Attendance</x-button>
            @can('reports.export')
                <x-button :href="route('attendance.report.export', request()->query())" variant="secondary" icon="download">Export CSV</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="card attendance-report-filter no-print">
        <form method="GET" action="{{ route('attendance.report') }}" class="attendance-report-grid" id="attendance-report-form">
            <input type="hidden" name="show" value="1">
            <label class="field"><span class="field-label">Month</span><input class="input" type="month" name="month" id="attendance-month" value="{{ $selectedMonth }}"></label>
            <label class="field"><span class="field-label">From</span><input class="input" type="date" name="from" id="attendance-from" value="{{ $month ? '' : $from }}"></label>
            <label class="field"><span class="field-label">To</span><input class="input" type="date" name="to" id="attendance-to" value="{{ $month ? '' : $to }}"></label>
            <label class="field"><span class="field-label">Class</span><select class="select" name="class_id" id="attendance-class"><option value="">All classes</option>@foreach($classes as $classOption)<option value="{{ $classOption->id }}" @selected((string)$classId === (string)$classOption->id)>{{ $classOption->name }}</option>@endforeach</select></label>

            <label class="field"><span class="field-label">Section</span><select class="select" name="section_id" id="attendance-section"><option value="">All sections</option>@foreach($sectionOptions as $sectionOption)<option value="{{ $sectionOption->id }}" data-class-ids="{{ implode(',', $sectionOption->class_ids) }}" @selected((string)$sectionId === (string)$sectionOption->id)>{{ $sectionOption->name }}</option>@endforeach</select></label>
            <label class="field attendance-student-field"><span class="field-label">Student Name / Roll</span><input class="input" type="search" name="q" id="attendance-student" value="{{ $studentSearch }}" placeholder="Enter student name or roll number" @readonly(!$classId)></label>
            <div class="attendance-report-actions"><button type="submit" class="btn btn-primary"><x-icon name="filter" :size="16" /> Show</button>@if($showResults)<button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>@endif<a href="{{ route('attendance.report') }}" class="btn btn-secondary"><x-icon name="x" :size="16" /> Clear</a></div>
        </form>
        <p class="field-hint attendance-month-hint">Selecting a month uses the full month and disables From / To.</p>
    </section>

    @if($searchMessage && $showResults)
        <div class="alert alert-warning" role="status">{{ $searchMessage }}</div>
    @endif

    @if($showResults && $studentSearch !== '')
        <section class="card attendance-student-result">
            <header class="attendance-student-heading">
                <div><span class="eyebrow">Student Attendance Info</span><h2>{{ $selectedStudent?->full_name ?? ($studentDailyRows->first()['student'] ?? 'Student') }}</h2><p>Student ID: {{ $selectedStudent?->student_code ?? ($studentDailyRows->first()['student_id'] ?? '—') }} · {{ $from }} to {{ $to }}</p></div>
                <div class="attendance-student-totals"><span><b>{{ $summary['records'] }}</b>Records</span><span><b>{{ $summary['present'] }}</b>Present / late</span><span><b>{{ $summary['absent'] }}</b>Absent</span></div>
            </header>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Roll</th><th>Class</th><th>Section</th><th>Status</th><th>Note</th></tr></thead><tbody>
                @forelse($studentDailyRows as $row)
                    <tr><td>{{ $row['date']->format('d M Y') }}</td><td>{{ $row['roll_no'] ?? '—' }}</td><td>{{ $row['class'] }}</td><td>{{ $row['section'] }}</td><td><x-badge :color="match($row['status']) { 'present' => 'success', 'late' => 'warning', 'absent' => 'danger', default => 'muted' }">{{ ucfirst($row['status']) }}</x-badge></td><td>{{ $row['note'] ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="table-empty">No attendance records match this student and period.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    @elseif($showResults)
        @forelse($classes->when($classId, fn($items) => $items->where('id', $classId)->values()) as $classOption)
            @php($classRows = $infoMatrixRows->where('class_id', $classOption->id)->values())
            <section class="card attendance-class-result">
                <div class="card-header"><div><span class="eyebrow">Class Attendance Info</span><h3 class="card-title">{{ $classOption->name }}</h3><p class="card-sub">{{ $from }} to {{ $to }} · {{ $classRows->count() }} student(s)</p></div></div>
                <div class="table-wrap attendance-matrix-wrap"><table class="attendance-matrix"><thead><tr><th>Student / Roll</th>@foreach($matrixDays as $date)<th>{{ \Illuminate\Support\Carbon::parse($date)->format('d') }}<small>{{ \Illuminate\Support\Carbon::parse($date)->format('M') }}</small></th>@endforeach<th>P</th><th>L</th><th>A</th><th>E</th></tr></thead><tbody>
                    @forelse($classRows as $row)
                        <tr><th>{{ $row['student'] }}<small>Roll {{ $row['roll_no'] ?? $row['student_id'] ?? '—' }} · {{ $row['section'] }}</small></th>@foreach($matrixDays as $date)@php($status = $row['statuses']->get($date))<td class="{{ $status ? 'status-'.$status : '' }}">{{ $status ? strtoupper(substr($status, 0, 1)) : '—' }}</td>@endforeach<td>{{ $row['present'] }}</td><td>{{ $row['late'] }}</td><td>{{ $row['absent'] }}</td><td>{{ $row['excused'] }}</td></tr>
                    @empty
                        <tr><td colspan="{{ count($matrixDays) + 5 }}" class="table-empty">No students are enrolled in this class and section.</td></tr>
                    @endforelse
                </tbody></table></div>
                <p class="attendance-legend">P Present · L Late · A Absent · E Excused · — No attendance entry</p>
            </section>
        @empty
            <div class="card table-empty">No classes are available for this report.</div>
        @endforelse
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('attendance-report-form');
    if (!form) return;
    const month = form.elements.month;
    const from = form.elements.from;
    const to = form.elements.to;
    const classField = form.elements.class_id;
    const section = form.elements.section_id;
    const student = form.elements.q;
    const syncDates = () => {
        const useMonth = Boolean(month.value);
        from.disabled = to.disabled = useMonth;
        if (useMonth) from.value = to.value = '';
    };
    const syncSections = () => {
        const classId = classField.value;
        student.readOnly = !classId;
        if (!classId) student.value = '';
        for (const option of section.options) {
            if (!option.value) continue;
            option.hidden = !classId || !(option.dataset.classIds || '').split(',').includes(classId);
            if (option.hidden && option.selected) section.value = '';
        }
    };
    month.addEventListener('change', syncDates);
    from.addEventListener('change', () => { month.value = ''; syncDates(); });
    to.addEventListener('change', () => { month.value = ''; syncDates(); });
    classField.addEventListener('change', syncSections);
    syncDates();
    syncSections();
})();
</script>
@endpush
