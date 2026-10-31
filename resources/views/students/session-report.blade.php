@extends('layouts.app')

@section('title', 'Session Report')

@section('content')
    <x-page-header eyebrow="Students" :title="'Session Report · '.$session->name" subtitle="Enrolment, fee collection and attendance summary for the academic year.">
        <x-slot:actions>
            @can('reports.export')
                <x-button :href="route('session-report.export', ['session_id' => $session->id])" variant="primary" icon="download">Export CSV</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid">
        <x-stat-card label="Enrolled" :value="number_format($totalEnrolled)" icon="users" tone="student" :meta="$activeStudents.' active'" />
        <x-stat-card label="New Admissions" :value="number_format($newAdmissions)" icon="user-plus" tone="collection" meta="Within session dates" />
        <x-stat-card label="Collected" :value="money($collected)" icon="circle-dollar" tone="collection" meta="Completed receipts" />
        <x-stat-card label="Outstanding" :value="money($outstanding)" icon="wallet" tone="pending" :meta="$attendancePercentage.'% attendance'" />
    </div>

    <div class="grid-main-side">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Class Breakdown</h3><p class="card-sub">{{ $classRows ? count($classRows) : 0 }} class(es) with enrolment</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Class</th><th>Sections</th><th class="num">Students</th><th class="num">Male</th><th class="num">Female</th><th class="num">Collected</th><th class="num">Outstanding</th></tr></thead>
                    <tbody>
                        @forelse ($classRows as $row)
                            <tr>
                                <td class="cell-title">{{ $row['class'] }}</td>
                                <td>{{ $row['sections'] ?: '—' }}</td>
                                <td class="num">{{ $row['students'] }}</td>
                                <td class="num">{{ $row['male'] }}</td>
                                <td class="num">{{ $row['female'] }}</td>
                                <td class="num text-success">{{ money($row['collected']) }}</td>
                                <td class="num {{ $row['outstanding'] > 0 ? 'text-danger' : '' }}">{{ money($row['outstanding']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">No enrolments in this session.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Attendance</h3><p class="card-sub">{{ $session->name }}</p></div></div>
                <div class="card-body">
                    <div class="kpi-row">
                        <div class="kpi"><div class="kpi-label">Records</div><div class="kpi-value">{{ number_format($attendanceTotal) }}</div></div>
                        <div class="kpi"><div class="kpi-label">Present %</div><div class="kpi-value text-success">{{ $attendancePercentage }}%</div></div>
                    </div>
                    <div class="divider"></div>
                    <div class="detail-list">
                        <div class="detail-row"><span class="detail-label">Session dates</span><span class="detail-value">{{ $session->start_date->format('d M Y') }} – {{ $session->end_date->format('d M Y') }}</span></div>
                        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">{{ ucfirst($session->status) }}</span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">Switch Session</h3></div></div>
                <div class="form-section">
                    <x-select name="session_id" label="Academic session" :options="\App\Models\AcademicSession::orderByDesc('start_date')->pluck('name', 'id')" :selected="$session->id" />
                    <p class="field-hint">Use the session selector in the top bar to change the active session everywhere, or export a specific session above.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
