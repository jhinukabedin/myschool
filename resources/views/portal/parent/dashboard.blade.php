@extends('layouts.portal')

@section('title', 'Parent Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">Parent portal</p>
            <h1>Welcome{{ $child ? ', '.$child->first_name.'\'s guardian' : '' }}</h1>
            <p>Monitor attendance, fees and published results for the children explicitly linked to your account.</p>
        </div>
        @if ($child)
            <div class="chips">
                <a class="btn btn-secondary btn-sm" href="{{ route('parent.results.index', $child) }}">Results</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('parent.fees.index', $child) }}">Fees</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('parent.attendance.index', $child) }}">Attendance</a>
            </div>
        @endif
    </div>

    @if (! $child)
        <div class="card"><div class="empty">
            <strong>No student is linked to this guardian account.</strong><br>
            Ask the school administration to link your account to your child's record. Parent accounts cannot search for students.
        </div></div>
    @else
        <div class="child-select" aria-label="Select child">
            @foreach ($children as $childOption)
                <a href="{{ route('parent.dashboard', ['child' => $childOption->id]) }}" @class(['active' => $childOption->id === $child->id])>
                    {{ $childOption->full_name }}
                </a>
            @endforeach
        </div>

        <div class="card profile-strip" style="margin-bottom:18px">
            <div class="avatar">
                @if ($child->photo_url)
                    <img src="{{ $child->photo_url }}" alt="{{ $child->full_name }}">
                @else
                    {{ Str::upper(Str::substr($child->first_name, 0, 1).Str::substr($child->last_name, 0, 1)) }}
                @endif
            </div>
            <div>
                <h2>{{ $child->full_name }}</h2>
                <div class="chips">
                    <span class="chip">Student ID: {{ $child->student_code }}</span>
                    @if ($enrollment)
                        <span class="chip">{{ $enrollment->class?->name }}</span>
                        <span class="chip">Section {{ $enrollment->section?->name ?? '—' }}</span>
                        <span class="chip">Roll {{ $enrollment->roll_no ?? '—' }}</span>
                    @endif
                    <span class="chip">{{ $session?->name ?? 'No session' }}</span>
                </div>
            </div>
            <a class="btn btn-secondary btn-sm" href="{{ route('parent.profile') }}">Guardian profile</a>
        </div>

        <div class="metric-grid">
            <div class="card stat"><div class="stat-label">Attendance</div><div class="stat-value">{{ number_format($attendance['percentage'], 1) }}%</div><div class="stat-meta">{{ $attendance['present'] + $attendance['late'] }} of {{ $attendance['records'] }} marked days</div></div>
            <div class="card stat"><div class="stat-label">Fees assigned</div><div class="stat-value">{{ money($fees['total_charged']) }}</div><div class="stat-meta">{{ $fees['assignments']->count() }} fee row(s)</div></div>
            <div class="card stat"><div class="stat-label">Verified paid</div><div class="stat-value" style="color:var(--success)">{{ money($fees['total_paid']) }}</div><div class="stat-meta">School-verified payments</div></div>
            <div class="card stat"><div class="stat-label">Outstanding</div><div class="stat-value" style="color:{{ $fees['balance'] > 0 ? 'var(--danger)' : 'var(--success)' }}">{{ money($fees['balance']) }}</div><div class="stat-meta">{{ $fees['overdue'] > 0 ? money($fees['overdue']).' overdue' : 'Nothing overdue' }}</div></div>
        </div>

        <div class="grid grid-2">
            <section class="card">
                <div class="card-head"><div><h2>Published results</h2><p>Latest examinations released by the school</p></div><a class="btn btn-secondary btn-sm" href="{{ route('parent.results.index', $child) }}">All results</a></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Examination</th><th class="num">GPA</th><th>Grade</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($results as $row)
                                <tr>
                                    <td class="title-cell"><strong>{{ $row['exam']->name }}</strong><span>{{ $row['published_at']?->format('d M Y') }}</span></td>
                                    <td class="num mono">{{ number_format($row['result']['gpa'], 2) }}</td>
                                    <td>{{ $row['result']['grade'] }}</td>
                                    <td><span class="badge {{ $row['result']['result'] === 'Pass' ? 'badge-success' : 'badge-danger' }}">{{ $row['result']['result'] }}</span></td>
                                    <td><a class="btn btn-secondary btn-sm" href="{{ route('parent.results.show', [$child, $row['exam']]) }}">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">No published results are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><div><h2>Fee balance</h2><p>Current outstanding fee rows</p></div><a class="btn btn-secondary btn-sm" href="{{ route('parent.fees.index', $child) }}">Full ledger</a></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Fee head</th><th>Due</th><th class="num">Balance</th></tr></thead>
                        <tbody>
                            @forelse ($fees['assignments']->where('balance', '>', 0)->take(6) as $assignment)
                                <tr>
                                    <td>{{ $assignment->category?->name ?? 'Fee' }}</td>
                                    <td class="mono">{{ $assignment->due_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="num mono">{{ money($assignment->balance) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty">No outstanding fees.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid grid-2" style="margin-top:18px">
            <section class="card">
                <div class="card-head"><div><h2>Recent attendance</h2><p>Last marked days</p></div><a class="btn btn-secondary btn-sm" href="{{ route('parent.attendance.index', $child) }}">Details</a></div>
                <div class="list">
                    @forelse ($attendance['recent'] as $record)
                        <div class="list-item">
                            <div><strong>{{ $record->attendance_date?->format('d M Y') }}</strong><small>{{ $record->class?->name }}</small></div>
                            <span class="badge {{ $record->status === 'present' ? 'badge-success' : ($record->status === 'absent' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst($record->status) }}</span>
                        </div>
                    @empty
                        <div class="empty">No attendance has been marked.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="card-head"><div><h2>Notices</h2><p>School announcements</p></div></div>
                <div class="list">
                    @forelse ($notices as $notice)
                        <div class="list-item">
                            <div><strong>{{ $notice->title }}</strong><small>{{ Str::limit($notice->plain_body, 100) }}</small></div>
                            <span class="notice-date">{{ $notice->publish_at?->format('d') ?? $notice->created_at?->format('d') }}<br>{{ $notice->publish_at?->format('M') ?? $notice->created_at?->format('M') }}</span>
                        </div>
                    @empty
                        <div class="empty">No notices have been published.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="card" style="margin-top:18px">
            <div class="card-head"><div><h2>Examination schedule</h2><p>Published papers for the class</p></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Subject</th><th>Examination</th><th>Date</th><th>Time</th></tr></thead>
                    <tbody>
                        @forelse ($exams as $paper)
                            <tr>
                                <td>{{ $paper->subject?->name }}</td>
                                <td>{{ $paper->examination?->name }}</td>
                                <td class="mono">{{ $paper->exam_date?->format('d M Y') ?? 'Not scheduled' }}</td>
                                <td class="mono">{{ $paper->start_time ? substr($paper->start_time, 0, 5) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">No published examination schedule is available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
