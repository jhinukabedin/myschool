@extends('layouts.app')

@section('title', __('Routine Days & Time Slots'))

@section('content')
    <x-page-header eyebrow="{{ __('Academic management') }}" title="{{ __('Days & Time Slots') }}"
        subtitle="{{ __('Set working days once, then use shared default periods or customize class times where needed.') }}">
        <x-slot:actions>
            <x-button :href="route('routines.index')" variant="secondary" icon="arrow-left">{{ __('Class routines') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ __('Choose a time schedule') }}</h3>
                <p class="card-sub">{{ __('Shared defaults keep setup quick. Customize only the classes with different hours.') }}</p>
            </div>
            <form method="GET" action="{{ route('routines.settings') }}" class="filter-bar" style="margin:0">
                <div class="field">
                    <label class="field-label" for="routine-class-select">{{ __('Apply times to') }}</label>
                    <select class="select" id="routine-class-select" name="class_id" onchange="this.form.submit()">
                        <option value="">{{ __('Shared default schedule') }}</option>
                        @foreach ($classes as $classOption)
                            <option value="{{ $classOption->id }}" @selected($selectedClass?->id === $classOption->id)>{{ $classOption->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="form-section">
            @if (! $selectedClass)
                <div class="info-box">{{ __('These periods are the default for classes that do not have a custom schedule.') }}</div>
            @elseif ($hasCustomSlots)
                <div class="info-box">{{ __('You are editing') }} <strong>{{ $selectedClass->name }}</strong>{{ __("'s own periods. Changes here affect only this class.") }}</div>
            @elseif ($slots->isNotEmpty())
                <div class="info-box" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
                    <span><strong>{{ $selectedClass->name }}</strong> {{ __('currently uses shared periods. Copy them once to change this class independently.') }}</span>
                    <form method="POST" action="{{ route('routines.settings.customize', $selectedClass) }}">
                        @csrf
                        <x-button type="submit" variant="primary" icon="clock">{{ __('Set class-specific times') }}</x-button>
                    </form>
                </div>
            @else
                <div class="info-box"><strong>{{ $selectedClass->name }}</strong> {{ __('has no periods yet. Add its first period below; other classes will keep their own times.') }}</div>
            @endif
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('Working days') }}</h3>
                    <p class="card-sub">{{ __('Selected days become the rows of every timetable, top to bottom in this order.') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('routines.settings.days') }}">
                @csrf
                @method('PUT')
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th style="width:74px">{{ __('Show') }}</th><th>{{ __('Day') }}</th><th style="width:130px">{{ __('Display order') }}</th></tr></thead>
                        <tbody>
                            @foreach ($allDays as $dayKey => $dayLabel)
                                @php
                                    $isSelected = array_key_exists($dayKey, $days);
                                    $position = array_search($dayKey, array_keys($days), true);
                                    $defaultOrder = $position === false ? array_search($dayKey, array_keys($allDays), true) + 1 : $position + 1;
                                @endphp
                                <tr>
                                    <td>
                                        <label class="checkbox">
                                            <input type="checkbox" name="days[]" value="{{ $dayKey }}" @checked($isSelected)>
                                            <span>{{ $isSelected ? __('Shown') : __('Hidden') }}</span>
                                        </label>
                                    </td>
                                    <td class="cell-title">{{ __($dayLabel) }}</td>
                                    <td><input class="input" type="number" min="1" max="99" name="day_order[{{ $dayKey }}]" value="{{ old('day_order.'.$dayKey, $defaultOrder) }}" style="width:90px"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                    <p class="field-hint" style="margin:0">{{ __('At least one day must remain selected.') }}</p>
                    <x-button type="submit" variant="primary" icon="save">{{ __('Save working days') }}</x-button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('Period rules') }}</h3>
                    <p class="card-sub">{{ __('Safe defaults and constraints for the timetable.') }}</p>
                </div>
            </div>
            <div class="form-section">
                <div class="stack">
                    <div class="info-box">{{ __('Overlapping teaching periods are rejected. Two periods may meet at a boundary, for example 08:45 ends and 08:45 starts.') }}</div>
                    <div class="info-box">{{ __('Lunch, break and assembly periods are non-teaching. They render as distinct cells in every day row and cannot contain a subject.') }}</div>
                    <div class="info-box">{{ __('Deleting or disabling a period that already has timetable cells is blocked until those cells are cleared.') }}</div>
                    <div class="info-box">{{ __('Working days are shared. Time periods use shared defaults unless a class has its own schedule.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-header">
            <div>
                    <h3 class="card-title">{{ $selectedClass && $hasCustomSlots ? __(':class time slots', ['class' => $selectedClass->name]) : ($selectedClass ? __('Shared periods used by :class', ['class' => $selectedClass->name]) : __('Default time slots')) }}</h3>
                <p class="card-sub">{{ __(':count period(s), shown across the top of the timetable in display order', ['count' => $slots->count()]) }}</p>
            </div>
            <x-badge color="violet">{{ __(':count active', ['count' => $slots->where('is_active', true)->count()]) }}</x-badge>
        </div>

        @if ($slots->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th style="width:105px">{{ __('Order') }}</th><th>{{ __('Period') }}</th><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th class="num">{{ __('Cells') }}</th><th>{{ __('Status') }}</th><th class="actions">{{ __('Actions') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($slots as $slot)
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:4px">
                                        <span class="mono" style="min-width:18px">{{ $loop->iteration }}</span>
                                        @if (! $selectedClass || $hasCustomSlots || $slots->isEmpty())<form method="POST" action="{{ route('routines.time-slots.move', $slot) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="btn-icon" title="{{ __('Move earlier') }}" @disabled($loop->first)><x-icon name="arrow-up" :size="14" /></button>
                                        </form>
                                        <form method="POST" action="{{ route('routines.time-slots.move', $slot) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="btn-icon" title="{{ __('Move later') }}" @disabled($loop->last)><x-icon name="arrow-down" :size="14" /></button>
                                        </form>@endif
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-title">{{ $slot->name }}</div>
                                    @if (! $selectedClass || $hasCustomSlots || $slots->isEmpty())<details style="margin-top:4px">
                                        <summary class="cell-sub" style="cursor:pointer">{{ __('Edit period') }}</summary>
                                        <form method="POST" action="{{ route('routines.time-slots.update', $slot) }}" style="display:grid;gap:6px;margin-top:8px;min-width:260px">
                                            @csrf
                                            @method('PUT')
                                            <label class="field"><span class="field-label">{{ __('Name') }}</span><input class="input" name="name" value="{{ $slot->name }}" required maxlength="60"></label>
                                            <div style="display:flex;gap:6px">
                                                <label class="field" style="width:110px"><span class="field-label">{{ __('Start') }}</span><input class="input" type="time" name="start_time" value="{{ $slot->start_label }}" required></label>
                                                <label class="field" style="width:110px"><span class="field-label">{{ __('End') }}</span><input class="input" type="time" name="end_time" value="{{ $slot->end_label }}" required></label>
                                            </div>
                                            <label class="field"><span class="field-label">{{ __('Type') }}</span>
                                                <select class="select" name="type">
                                                    @foreach ($types as $value => $label)
                                                        <option value="{{ $value }}" @selected($slot->type === $value)>{{ __($label) }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="field" style="width:110px"><span class="field-label">{{ __('Sort order') }}</span><input class="input" type="number" min="0" max="9999" name="sort_order" value="{{ $slot->sort_order }}"></label>
                                            <input type="hidden" name="is_active" value="0">
                                            <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($slot->is_active)> <span>{{ __('Active period') }}</span></label>
                                            <button class="btn btn-secondary btn-sm" type="submit">{{ __('Save period') }}</button>
                                        </form>
                                    </details>@endif
                                </td>
                                <td class="mono">{{ $slot->time_range }}</td>
                                <td><x-badge :color="$slot->isTeaching() ? 'info' : 'warning'">{{ __($slot->type_label) }}</x-badge></td>
                                <td class="num">{{ $slot->entries_count }}</td>
                                <td><x-badge :color="$slot->is_active ? 'success' : 'muted'">{{ $slot->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                                <td class="actions">
                                    @if (! $selectedClass || $hasCustomSlots || $slots->isEmpty())<form method="POST" action="{{ route('routines.time-slots.destroy', $slot) }}" onsubmit="return confirm(@js(__('Delete this time slot?')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="{{ __('Delete period') }}"><x-icon name="trash" :size="15" /></button>
                                    </form>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="form-section">
                <div class="info-box">{{ __('No time slots yet. Add the first teaching period below.') }}</div>
            </div>
        @endif

        @if (! $selectedClass || $hasCustomSlots || $slots->isEmpty())
        <div class="form-section">
            <div class="form-section-title" style="margin-bottom:14px"><x-icon name="plus" :size="14" /> {{ __('Add a time slot') }}</div>
            <form method="POST" action="{{ route('routines.time-slots.store') }}">
                @csrf
                @if ($selectedClass)<input type="hidden" name="class_id" value="{{ $selectedClass->id }}">@endif
                <div class="form-grid three">
                    <x-input name="name" :label="__('Period name / number')" :placeholder="__('e.g. Period 1')" required />
                    <x-input name="start_time" :label="__('Start time')" type="time" required />
                    <x-input name="end_time" :label="__('End time')" type="time" required />
                    <x-select name="type" :label="__('Period type')" :options="$types" :selected="'class'" :placeholder="false" required />
                    <x-input name="sort_order" :label="__('Display order')" type="number" min="0" max="9999" :value="$nextOrder" :help="__('Lower numbers appear first.')" />
                    <div class="field">
                        <span class="field-label">{{ __('Status') }}</span>
                        <input type="hidden" name="is_active" value="1">
                        <label class="checkbox"><input type="checkbox" checked disabled> <span>{{ __('New periods are active') }}</span></label>
                    </div>
                </div>
                <div style="margin-top:16px">
                    <x-button type="submit" variant="outline" icon="plus">{{ __('Add a time slot') }}</x-button>
                </div>
            </form>
        </div>
        @endif
    </div>
@endsection
