@extends('layouts.portal')

@section('title', 'Leave')

@section('content')
    <div class="metric-grid" style="margin-bottom:16px">
        <div class="card stat"><div class="stat-label">{{ __('Pending') }}</div><div class="stat-value">{{ $summary['pending'] }}</div><div class="stat-meta">{{ __('Awaiting approval') }}</div></div>
        <div class="card stat"><div class="stat-label">{{ __('Approved') }}</div><div class="stat-value">{{ $summary['approved'] }}</div><div class="stat-meta">{{ __('All time') }}</div></div>
        <div class="card stat"><div class="stat-label">{{ __('Approved days') }}</div><div class="stat-value">{{ $summary['days'] }}</div><div class="stat-meta">{{ __('Total') }}</div></div>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>{{ __('Apply for leave') }}</h2><p>{{ __('Your request goes to the administration for approval') }}</p></div></div>
            <form method="POST" action="{{ route('staff-portal.leaves.store') }}">
                @csrf
                <div class="card-body">
                    <div class="stack">
                        <label class="field">
                            <span class="field-label">{{ __('Leave type') }}<span class="required-mark">*</span></span>
                            <select class="select" name="leave_type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('leave_type') === $value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid grid-2">
                            <label class="field">
                                <span class="field-label">{{ __('Start date') }}<span class="required-mark">*</span></span>
                                <input class="input" type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                                @error('start_date')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="field">
                                <span class="field-label">{{ __('End date') }}<span class="required-mark">*</span></span>
                                <input class="input" type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                                @error('end_date')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                        </div>
                        <label class="field">
                            <span class="field-label">{{ __('Reason') }}<span class="required-mark">*</span></span>
                            <textarea class="textarea" name="reason" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea>
                            @error('reason')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </div>
                <div class="card-foot" style="display:flex;justify-content:flex-end;padding:0 18px 16px">
                    <button type="submit" class="btn btn-primary">{{ __('Submit application') }}</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>{{ __('My leave history') }}</h2><p>{{ __('Most recent first') }}</p></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>{{ __('Type') }}</th><th class="num">{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($leaves as $leave)
                            <tr>
                                <td class="title-cell">
                                    <strong>{{ __($leave->type_label) }}</strong>
                                    <span>{{ $leave->start_date?->format('d M Y') }} – {{ $leave->end_date?->format('d M Y') }}</span>
                                    @if ($leave->decision_note)<span>{{ $leave->decision_note }}</span>@endif
                                </td>
                                <td class="num">{{ $leave->days }}</td>
                                <td><span class="badge {{ $leave->status === 'approved' ? 'badge-success' : ($leave->status === 'pending' ? 'badge-warning' : ($leave->status === 'rejected' ? 'badge-danger' : 'badge-muted')) }}">{{ __($leave->status_label) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">{{ __('You have not applied for leave yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="padding:12px 18px">{{ $leaves->links() }}</div>
        </section>
    </div>
@endsection
