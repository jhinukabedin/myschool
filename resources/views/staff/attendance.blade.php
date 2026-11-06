@extends('layouts.app')

@section('title', __('Staff Attendance'))

@section('content')
    <x-page-header eyebrow="{{ __('Staff') }}" title="{{ __('Staff Attendance') }}" :subtitle="__('Mark daily attendance for teaching and non-teaching staff.')">
        <x-slot:actions>
            <x-button :href="route('staff.leaves.index')" variant="secondary" icon="calendar">{{ __('Staff Leave') }}</x-button>
            <x-button :href="route('staff.index')" variant="ghost" icon="users">{{ __('Staff Directory') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
        <x-stat-card :label="__('Staff')" :value="number_format($summary['total'])" icon="users" tone="student" :meta="__('Active employees')" />
        <x-stat-card :label="__('Present')" :value="number_format($summary['present'])" icon="check-circle" tone="collection" :meta="__('Marked present')" />
        <x-stat-card :label="__('Absent')" :value="number_format($summary['absent'])" icon="x-circle" tone="expense" :meta="__('Marked absent')" />
        <x-stat-card :label="__('On leave')" :value="number_format($summary['leave'])" icon="calendar" tone="pending" :meta="__('Approved leave')" />
        <x-stat-card :label="__('Unmarked')" :value="number_format($summary['unmarked'])" icon="clock" tone="pending" :meta="__('No record yet')" />
    </div>

    @if ($holiday)
        <div class="alert alert-info" role="status" style="display:flex;gap:10px;align-items:flex-start">
            <span class="alert-icon"><x-icon name="calendar" :size="17" /></span>
            <div>
                <strong>{{ __('Holiday') }}: {{ $holiday['name'] }}</strong>
                <div style="font-size:12px;margin-top:2px">{{ __('No staff attendance is expected on this date.') }}</div>
            </div>
        </div>
    @endif

    <form method="GET" action="{{ route('staff.attendance.index') }}" class="card" style="padding:14px">
        <div class="filter-bar" style="margin:0">
            <div class="field">
                <input class="input" type="date" name="date" value="{{ $date }}" aria-label="{{ __('Date') }}">
            </div>
            <div class="field">
                <select class="select" name="department_id">
                    <option value="">{{ __('All departments') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
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
            <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
            @if (request()->hasAny(['date', 'department_id', 'status']))
                <x-button :href="route('staff.attendance.index')" variant="ghost" icon="x">{{ __('Reset') }}</x-button>
            @endif
        </div>
    </form>

    <form method="POST" action="{{ route('staff.attendance.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</h3><p class="card-sub">{{ $staff->count() }} {{ __('staff member(s)') }}</p></div>
                @can('staff.manage')
                    <x-button type="submit" variant="primary" icon="save" :disabled="$holiday !== null">{{ __('Save attendance') }}</x-button>
                @endcan
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Staff') }}</th>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th style="min-width:220px">{{ __('Note') }}</th>
                            <th>{{ __('Marked by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($staff as $member)
                            @php($record = $records->get($member->id))
                            <tr>
                                <td>
                                    <div class="cell-main">
                                        <span class="table-avatar">{{ Str::substr($member->full_name, 0, 1) }}</span>
                                        <div>
                                            <div class="cell-title">{{ $member->full_name }}</div>
                                            <div class="cell-sub">{{ $member->employee_no }} · {{ $member->designation?->name ?? __('No designation') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $member->department?->name ?? '—' }}</td>
                                <td>
                                    <select class="select" name="statuses[{{ $member->id }}]" @disabled(! auth()->user()?->can('staff.manage'))>
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(($record?->status ?? (in_array($member->id, $onLeaveIds, true) ? 'leave' : 'present')) === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input class="input" type="text" name="notes[{{ $member->id }}]" value="{{ $record?->note }}" maxlength="255" placeholder="{{ __('Optional note') }}" @disabled(! auth()->user()?->can('staff.manage'))>
                                </td>
                                <td class="muted" style="font-size:11.5px">
                                    @if ($record)
                                        {{ $record->markedBy?->name ?? __('System') }}
                                        <div class="cell-sub">{{ $record->updated_at?->format('d M, h:i A') }}</div>
                                    @else
                                        {{ __('Not marked') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-empty">{{ __('No active staff match these filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @can('staff.manage')
                <div class="form-section" style="display:flex;justify-content:flex-end">
                    <x-button type="submit" variant="primary" icon="save" :disabled="$holiday !== null">{{ __('Save attendance') }}</x-button>
                </div>
            @endcan
        </div>
    </form>
@endsection
