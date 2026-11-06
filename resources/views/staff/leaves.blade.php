@extends('layouts.app')

@section('title', __('Staff Leave'))

@section('content')
    <x-page-header eyebrow="{{ __('Staff') }}" title="{{ __('Staff Leave') }}" :subtitle="__('Record leave applications, approve or reject them, and see who is on leave today.')">
        <x-slot:actions>
            <x-button :href="route('staff.attendance.index')" variant="secondary" icon="calendar-check">{{ __('Staff Attendance') }}</x-button>
            @can('staff.manage')
                <x-button :href="route('staff.leaves.index', ['new' => 1])" variant="primary" icon="plus">{{ __('Record leave') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="alert alert-success" role="status">
            <span class="alert-icon"><x-icon name="check-circle" :size="17" /></span>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <div class="stat-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
        <x-stat-card :label="__('Pending')" :value="number_format($summary['pending'])" icon="clock" tone="pending" :meta="__('Awaiting approval')" />
        <x-stat-card :label="__('Approved')" :value="number_format($summary['approved'])" icon="check-circle" tone="collection" :meta="__('All time')" />
        <x-stat-card :label="__('Rejected')" :value="number_format($summary['rejected'])" icon="x-circle" tone="expense" :meta="__('All time')" />
        <x-stat-card :label="__('On leave today')" :value="number_format($summary['on_leave_today'])" icon="calendar" tone="student" :meta="now()->format('d M Y')" />
        <x-stat-card :label="__('Approved days')" :value="number_format($summary['days_approved'])" icon="list" tone="student" :meta="__('Total leave days')" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ __('Leave Register') }}</h3><p class="card-sub">{{ $leaves->total() }} {{ __('record(s)') }}</p></div>
            <form method="GET" action="{{ route('staff.leaves.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <select class="select" name="staff_id">
                        <option value="">{{ __('All staff') }}</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected(request('staff_id') == $member->id)>{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><input class="input" type="date" name="from" value="{{ request('from') }}" aria-label="{{ __('From date') }}"></div>
                <div class="field"><input class="input" type="date" name="to" value="{{ request('to') }}" aria-label="{{ __('To date') }}"></div>
                <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
                @if (request()->hasAny(['staff_id', 'status', 'from', 'to']))
                    <x-button :href="route('staff.leaves.index')" variant="ghost" icon="x">{{ __('Reset') }}</x-button>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Staff') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th class="num">{{ __('Days') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Decided by') }}</th>
                        <th class="actions">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leaves as $leave)
                        <tr>
                            <td>
                                <div class="cell-title">{{ $leave->staff?->full_name ?? __('Unknown') }}</div>
                                <div class="cell-sub">{{ $leave->staff?->employee_no }} · {{ $leave->staff?->department?->name ?? __('No department') }}</div>
                            </td>
                            <td>{{ __($leave->type_label) }}</td>
                            <td class="muted" style="white-space:nowrap">
                                {{ $leave->start_date?->format('d M Y') }} → {{ $leave->end_date?->format('d M Y') }}
                            </td>
                            <td class="num">{{ $leave->days }}</td>
                            <td style="max-width:260px;white-space:normal" class="muted">{{ Str::limit($leave->reason, 90) }}</td>
                            <td>
                                <x-badge :color="match ($leave->status) { 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'muted' }">{{ __($leave->status_label) }}</x-badge>
                                @if ($leave->decision_note)
                                    <div class="cell-sub">{{ Str::limit($leave->decision_note, 40) }}</div>
                                @endif
                            </td>
                            <td class="muted" style="font-size:11.5px">
                                @if ($leave->approvedBy)
                                    {{ $leave->approvedBy->name }}
                                    <div class="cell-sub">{{ $leave->approved_at?->format('d M Y') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="actions" style="white-space:nowrap">
                                @can('staff.manage')
                                    @if ($leave->status === 'pending')
                                        <form method="POST" action="{{ route('staff.leaves.approve', $leave) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" title="{{ __('Approve') }}"><x-icon name="check" :size="13" /> {{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.leaves.reject', $leave) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm" title="{{ __('Reject') }}"><x-icon name="x" :size="13" /> {{ __('Reject') }}</button>
                                        </form>
                                    @endif
                                    @if (in_array($leave->status, ['pending', 'approved'], true))
                                        <form method="POST" action="{{ route('staff.leaves.cancel', $leave) }}" style="display:inline"
                                              onsubmit="return confirm(@js(__('Cancel this leave record?')))">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm" title="{{ __('Cancel') }}">{{ __('Cancel') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">{{ __('No leave records match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $leaves->links() }}
    </div>

    @can('staff.manage')
        <x-modal name="record-leave" :title="__('Record staff leave')" maxWidth="560px" :open="request()->boolean('new') || $errors->any()">
            <form method="POST" action="{{ route('staff.leaves.store') }}">
                @csrf
                <div class="stack">
                    <x-select name="staff_id" :label="__('Staff member')" :options="$staff->mapWithKeys(fn ($member) => [$member->id => $member->full_name.' ('.$member->employee_no.')'])" :selected="old('staff_id')" required :placeholder="__('Select staff')" />
                    <x-select name="leave_type" :label="__('Leave type')" :options="collect($types)->map(fn ($label) => __($label))->all()" :selected="old('leave_type', 'casual')" :placeholder="false" required />
                    <div class="form-grid">
                        <x-input name="start_date" :label="__('Start date')" type="date" :value="old('start_date', now()->toDateString())" required />
                        <x-input name="end_date" :label="__('End date')" type="date" :value="old('end_date', now()->toDateString())" required />
                    </div>
                    <label class="field">
                        <span class="field-label">{{ __('Reason') }}<span class="required-mark">*</span></span>
                        <textarea class="textarea" name="reason" rows="3" maxlength="1000" required placeholder="{{ __('Why is this leave needed?') }}">{{ old('reason') }}</textarea>
                        @error('reason')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <p class="field-hint" style="margin:0">{{ __('The request starts as pending until it is approved or rejected.') }}</p>
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">{{ __('Record leave') }}</x-button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
