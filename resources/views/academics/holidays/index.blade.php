@extends('layouts.app')

@section('title', __('Holidays & Academic Calendar'))

@section('content')
    <x-page-header eyebrow="{{ __('Academics') }}" title="{{ __('Holidays & Academic Calendar') }}" :subtitle="__('Weekly off days and holidays pause attendance for the affected audience.')">
        <x-slot:actions>
            @can('academics.manage')
                <button type="button" class="btn btn-primary" @click="$dispatch('open-modal', 'new-holiday')">
                    <x-icon name="plus" :size="15" /> {{ __('Add holiday') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <div><h3 class="card-title">{{ __('Calendar') }}</h3><p class="card-sub">{{ $holidays->total() }} {{ __('entr(y/ies)') }} in {{ $year }}</p></div>
                    <form method="GET" action="{{ route('holidays.index') }}" class="filter-bar" style="margin:0">
                        <div class="field">
                            <select class="select" name="year">
                                @foreach ($years as $option)
                                    <option value="{{ $option }}" @selected((int) $option === $year)>{{ $option }}</option>
                                @endforeach
                                @if ($years->isEmpty())
                                    <option value="{{ now()->year }}" selected>{{ now()->year }}</option>
                                @endif
                            </select>
                        </div>
                        <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>{{ __('Holiday') }}</th><th>{{ __('Type') }}</th><th>{{ __('Period') }}</th><th class="num">{{ __('Days') }}</th><th>{{ __('Applies to') }}</th><th>{{ __('Status') }}</th><th class="actions"></th></tr></thead>
                        <tbody>
                            @forelse ($holidays as $holiday)
                                @php($days = $holiday->start_date->diffInDays($holiday->end_date) + 1)
                                <tr>
                                    <td>
                                        <div class="cell-title">{{ $holiday->name }}</div>
                                        @if ($holiday->description)<div class="cell-sub">{{ Str::limit($holiday->description, 60) }}</div>@endif
                                    </td>
                                    <td>{{ __($holiday->type_label) }}</td>
                                    <td class="muted" style="white-space:nowrap">{{ $holiday->start_date->format('d M Y') }} → {{ $holiday->end_date->format('d M Y') }}</td>
                                    <td class="num">{{ $days }}</td>
                                    <td>{{ ucfirst($holiday->applies_to) }}</td>
                                    <td>
                                        @if ($holiday->end_date->isBefore(now()->startOfDay()))
                                            <x-badge color="muted">{{ __('Past') }}</x-badge>
                                        @elseif ($holiday->start_date->isAfter(now()->endOfDay()))
                                            <x-badge color="info">{{ __('Upcoming') }}</x-badge>
                                        @else
                                            <x-badge color="success">{{ __('Today') }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="actions">
                                        @can('academics.manage')
                                            <button type="button" class="btn-icon" title="{{ __('Edit') }}" @click="$dispatch('open-modal', 'edit-holiday-{{ $holiday->id }}')"><x-icon name="edit" :size="15" /></button>
                                            <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" style="display:inline" onsubmit="return confirm(@js(__('Remove this holiday?')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" :size="15" /></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="table-empty">{{ __('No holidays recorded for this year.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $holidays->links() }}
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Weekly off days') }}</h3><p class="card-sub">{{ __('Attendance is not required on these weekdays.') }}</p></div></div>
                <form method="POST" action="{{ route('holidays.weekdays') }}">
                    @csrf
                    <div class="form-section">
                        <div class="stack" style="gap:6px">
                            @foreach ($weekdays as $value => $label)
                                <label class="due-row" style="cursor:pointer;align-items:center;gap:10px">
                                    <input type="checkbox" name="weekdays[]" value="{{ $value }}" @checked(in_array($value, $weeklyOff, true)) @disabled(! auth()->user()?->can('academics.manage'))>
                                    <span style="flex:1">{{ __($label) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @can('academics.manage')
                            <div style="display:flex;justify-content:flex-end;margin-top:12px">
                                <x-button type="submit" variant="primary" icon="save">{{ __('Save weekly off') }}</x-button>
                            </div>
                        @endcan
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">{{ __('Upcoming') }}</h3></div></div>
                <div class="card-body">
                    <div class="stack" style="gap:8px">
                        @forelse ($upcoming as $holiday)
                            <div class="due-row">
                                <div class="due-info">
                                    <span class="table-avatar"><x-icon name="calendar" :size="14" /></span>
                                    <div>
                                        <div class="cell-title">{{ $holiday->name }}</div>
                                        <div class="cell-sub">{{ $holiday->start_date->format('d M') }} – {{ $holiday->end_date->format('d M Y') }} · {{ __($holiday->type_label) }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="muted" style="margin:0;font-size:12px">{{ __('No upcoming holidays.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="info-box">{{ __('Holidays pause attendance for students, staff or both. Weekly off days are excluded automatically, so attendance registers stay accurate without manual marking.') }}</div>
                </div>
            </div>
        </div>
    </div>

    @can('academics.manage')
        <x-modal name="new-holiday" :title="__('Add holiday')" maxWidth="560px">
            @include('academics.holidays.form', ['holiday' => new \App\Models\Holiday()])
        </x-modal>

        @foreach ($holidays as $holiday)
            <x-modal name="edit-holiday-{{ $holiday->id }}" :title="__('Edit holiday')" maxWidth="560px">
                @include('academics.holidays.form', ['holiday' => $holiday])
            </x-modal>
        @endforeach
    @endcan
@endsection
