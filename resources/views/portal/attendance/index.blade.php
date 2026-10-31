@extends('layouts.portal')

@section('title', 'Attendance')
@section('banner-subtitle', 'উপস্থিতি ও মাসিক অগ্রগতি')

@section('content')
    @if (isset($children) && $children->count() > 1)
        <div class="portal-filter">
            <div class="field">
                <label for="child-switch">Child</label>
                <select id="child-switch" onchange="if(this.value) window.location.href=this.value">
                    @foreach ($children as $childOption)
                        <option value="{{ route('parent.attendance.index', $childOption) }}" @selected($childOption->id === $student->id)>{{ $childOption->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="portal-panel">
        <h2>উপস্থিতির সারাংশ</h2>
        <div class="portal-panel-body">
            <div class="portal-cards">
                <div class="portal-stat">
                    <strong>{{ number_format($attendance['percentage'], 0) }}%</strong>
                    <span>Overall Attendance</span>
                </div>
                <div class="portal-stat">
                    <strong>{{ $attendance['present'] }}</strong>
                    <span>Present Days</span>
                </div>
                <div class="portal-stat">
                    <strong>{{ $attendance['absent'] }}</strong>
                    <span>Absent Days</span>
                </div>
                <div class="portal-stat">
                    <strong>{{ $attendance['late'] }}</strong>
                    <span>Late</span>
                </div>
            </div>
            <div style="margin-top:20px">
                <b>{{ $session?->name ?? 'Current session' }}</b>
                <div class="progress" style="margin-top:8px">
                    <i style="width: {{ min(100, $attendance['percentage']) }}%"></i>
                </div>
                <p class="small muted" style="margin:9px 0 0">
                    @if ($enrollment)
                        {{ $enrollment->class?->name }} · Section {{ $enrollment->section?->name ?? '—' }} · Roll {{ $enrollment->roll_no ?? '—' }}
                    @else
                        No current enrollment is recorded.
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>মাসভিত্তিক উপস্থিতি</h2>
        <div class="portal-panel-body">
            <div class="table-wrap">
                <table class="portal-table">
                    <thead>
                        <tr><th>মাস</th><th>কার্যদিবস</th><th>উপস্থিত</th><th>অনুপস্থিত</th><th>হার</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($attendance['monthly'] as $month)
                            <tr>
                                <td>{{ $month['label'] }}</td>
                                <td>{{ $month['total'] }}</td>
                                <td>{{ $month['present'] + $month['late'] }}</td>
                                <td>{{ $month['absent'] }}</td>
                                <td>{{ number_format($month['percentage'], 0) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">No attendance has been marked yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>সাম্প্রতিক উপস্থিতি</h2>
        <div class="portal-panel-body">
            <div class="table-wrap">
                <table class="portal-table">
                    <thead><tr><th>তারিখ</th><th>শ্রেণি</th><th>অবস্থা</th><th>মন্তব্য</th></tr></thead>
                    <tbody>
                        @forelse ($attendance['recent'] as $record)
                            <tr>
                                <td class="mono">{{ $record->attendance_date?->format('d M Y') }}</td>
                                <td>{{ $record->class?->name }}</td>
                                <td>
                                    <span class="badge {{ $record->status === 'present' ? 'badge-success' : ($record->status === 'absent' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst($record->status) }}</span>
                                </td>
                                <td class="muted">{{ $record->note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">No attendance has been marked.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
