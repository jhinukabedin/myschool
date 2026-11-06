@extends('layouts.portal')

@section('title', 'Staff Dashboard')

@section('content')
    @if ($holiday)
        <div class="alert alert-info" style="margin-bottom:16px">
            <strong>{{ __('Holiday') }}:</strong> {{ $holiday['name'] }} — {{ __('no attendance is required today.') }}
        </div>
    @endif

    <div class="card profile-strip" style="margin-bottom:18px">
        <div class="avatar">
            @if ($staff->photo_path)
                <img src="{{ asset('storage/'.$staff->photo_path) }}" alt="{{ $staff->full_name }}">
            @else
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($staff->first_name, 0, 1).\Illuminate\Support\Str::substr($staff->last_name, 0, 1)) }}
            @endif
        </div>
        <div>
            <h2>{{ $staff->full_name }}</h2>
            <div class="chips">
                <span class="chip">{{ $staff->employee_no }}</span>
                @if ($staff->designation)<span class="chip">{{ $staff->designation->name }}</span>@endif
                @if ($staff->department)<span class="chip">{{ $staff->department->name }}</span>@endif
                <span class="chip">{{ ucfirst(str_replace('_', ' ', $staff->employment_type)) }}</span>
            </div>
        </div>
        <a class="btn btn-secondary btn-sm" href="{{ route('staff-portal.profile') }}">{{ __('My profile') }}</a>
    </div>

    <div class="metric-grid">
        <div class="card stat">
            <div class="stat-label">{{ __('This month salary') }}</div>
            <div class="stat-value">{{ $payroll ? money($payroll->net_salary) : '—' }}</div>
            <div class="stat-meta">{{ $payroll ? ucfirst($payroll->status).' · '.$payroll->month : __('Not generated yet') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-label">{{ __('Present this month') }}</div>
            <div class="stat-value">{{ (int) ($attendance['present'] ?? 0) + (int) ($attendance['late'] ?? 0) }}</div>
            <div class="stat-meta">{{ (int) ($attendance['absent'] ?? 0) }} {{ __('absent') }} · {{ (int) ($attendance['leave'] ?? 0) }} {{ __('on leave') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-label">{{ __('Pending leave') }}</div>
            <div class="stat-value">{{ $leave['pending'] }}</div>
            <div class="stat-meta">{{ $leave['approved'] }} {{ __('approved') }} · {{ $leave['days'] }} {{ __('days') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-label">{{ __('Payslips') }}</div>
            <div class="stat-value">{{ $recentPayments->count() ? money($recentPayments->first()->amount) : '—' }}</div>
            <div class="stat-meta">{{ __('Latest payment') }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>{{ __('Recent leave') }}</h2><p>{{ __('Your latest applications') }}</p></div><a class="btn btn-secondary btn-sm" href="{{ route('staff-portal.leaves.index') }}">{{ __('Apply / view') }}</a></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>{{ __('Type') }}</th><th class="num">{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($recentLeaves as $leave)
                            <tr>
                                <td class="title-cell">
                                    <strong>{{ __($leave->type_label) }}</strong>
                                    <span>{{ $leave->start_date?->format('d M') }} – {{ $leave->end_date?->format('d M Y') }}</span>
                                </td>
                                <td class="num">{{ $leave->days }}</td>
                                <td><span class="badge {{ $leave->status === 'approved' ? 'badge-success' : ($leave->status === 'pending' ? 'badge-warning' : ($leave->status === 'rejected' ? 'badge-danger' : 'badge-muted')) }}">{{ __($leave->status_label) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">{{ __('No leave applications yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>{{ __('Salary payments') }}</h2><p>{{ __('Recent installments') }}</p></div><a class="btn btn-secondary btn-sm" href="{{ route('staff-portal.payments.index') }}">{{ __('Full history') }}</a></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Date') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td>{{ $payment->payroll?->month }}</td>
                                <td class="muted">{{ $payment->paid_at?->format('d M Y') }}</td>
                                <td class="num">{{ money($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">{{ __('No salary payments recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
