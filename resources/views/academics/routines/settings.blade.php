@extends('layouts.app')

@section('title', 'Routine Days & Time Slots')

@section('content')
    <x-page-header eyebrow="Academic management" title="Days & Time Slots"
        subtitle="Configure which days appear as timetable rows and which periods appear as columns. Days are never hardcoded in the timetable view.">
        <x-slot:actions>
            <x-button :href="route('routines.index')" variant="secondary" icon="arrow-left">Class routines</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Working days</h3>
                    <p class="card-sub">Selected days become the rows of every timetable, top to bottom in this order.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('routines.settings.days') }}">
                @csrf
                @method('PUT')
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th style="width:74px">Show</th><th>Day</th><th style="width:130px">Display order</th></tr></thead>
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
                                            <span>{{ $isSelected ? 'Shown' : 'Hidden' }}</span>
                                        </label>
                                    </td>
                                    <td class="cell-title">{{ $dayLabel }}</td>
                                    <td><input class="input" type="number" min="1" max="99" name="day_order[{{ $dayKey }}]" value="{{ old('day_order.'.$dayKey, $defaultOrder) }}" style="width:90px"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-section" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                    <p class="field-hint" style="margin:0">At least one day must remain selected.</p>
                    <x-button type="submit" variant="primary" icon="save">Save working days</x-button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Period rules</h3>
                    <p class="card-sub">Safe defaults and constraints for the timetable.</p>
                </div>
            </div>
            <div class="form-section">
                <div class="stack">
                    <div class="info-box">Overlapping teaching periods are rejected. Two periods may meet at a boundary, for example 08:45 ends and 08:45 starts.</div>
                    <div class="info-box">Lunch, break and assembly periods are non-teaching. They render as distinct cells in every day row and cannot contain a subject.</div>
                    <div class="info-box">Deleting or disabling a period that already has timetable cells is blocked until those cells are cleared.</div>
                    <div class="info-box">Days and periods are shared by every class routine, so the grid stays consistent across classes.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-header">
            <div>
                <h3 class="card-title">Time slots</h3>
                <p class="card-sub">{{ $slots->count() }} period(s), shown across the top of the timetable in display order</p>
            </div>
            <x-badge color="violet">{{ $slots->where('is_active', true)->count() }} active</x-badge>
        </div>

        @if ($slots->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th style="width:105px">Order</th><th>Period</th><th>Time</th><th>Type</th><th class="num">Cells</th><th>Status</th><th class="actions">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($slots as $slot)
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:4px">
                                        <span class="mono" style="min-width:18px">{{ $loop->iteration }}</span>
                                        <form method="POST" action="{{ route('routines.time-slots.move', $slot) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="btn-icon" title="Move earlier" @disabled($loop->first)><x-icon name="arrow-up" :size="14" /></button>
                                        </form>
                                        <form method="POST" action="{{ route('routines.time-slots.move', $slot) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="btn-icon" title="Move later" @disabled($loop->last)><x-icon name="arrow-down" :size="14" /></button>
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-title">{{ $slot->name }}</div>
                                    <details style="margin-top:4px">
                                        <summary class="cell-sub" style="cursor:pointer">Edit period</summary>
                                        <form method="POST" action="{{ route('routines.time-slots.update', $slot) }}" style="display:grid;gap:6px;margin-top:8px;min-width:260px">
                                            @csrf
                                            @method('PUT')
                                            <label class="field"><span class="field-label">Name</span><input class="input" name="name" value="{{ $slot->name }}" required maxlength="60"></label>
                                            <div style="display:flex;gap:6px">
                                                <label class="field" style="width:110px"><span class="field-label">Start</span><input class="input" type="time" name="start_time" value="{{ $slot->start_label }}" required></label>
                                                <label class="field" style="width:110px"><span class="field-label">End</span><input class="input" type="time" name="end_time" value="{{ $slot->end_label }}" required></label>
                                            </div>
                                            <label class="field"><span class="field-label">Type</span>
                                                <select class="select" name="type">
                                                    @foreach ($types as $value => $label)
                                                        <option value="{{ $value }}" @selected($slot->type === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="field" style="width:110px"><span class="field-label">Sort order</span><input class="input" type="number" min="0" max="9999" name="sort_order" value="{{ $slot->sort_order }}"></label>
                                            <input type="hidden" name="is_active" value="0">
                                            <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($slot->is_active)> <span>Active period</span></label>
                                            <button class="btn btn-secondary btn-sm" type="submit">Save period</button>
                                        </form>
                                    </details>
                                </td>
                                <td class="mono">{{ $slot->time_range }}</td>
                                <td><x-badge :color="$slot->isTeaching() ? 'info' : 'warning'">{{ $slot->type_label }}</x-badge></td>
                                <td class="num">{{ $slot->entries_count }}</td>
                                <td><x-badge :color="$slot->is_active ? 'success' : 'muted'">{{ $slot->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                <td class="actions">
                                    <form method="POST" action="{{ route('routines.time-slots.destroy', $slot) }}" onsubmit="return confirm('Delete this time slot?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon" title="Delete period"><x-icon name="trash" :size="15" /></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="form-section">
                <div class="info-box">No time slots yet. Add the first teaching period below.</div>
            </div>
        @endif

        <div class="form-section">
            <div class="form-section-title" style="margin-bottom:14px"><x-icon name="plus" :size="14" /> Add a time slot</div>
            <form method="POST" action="{{ route('routines.time-slots.store') }}">
                @csrf
                <div class="form-grid three">
                    <x-input name="name" label="Period name / number" placeholder="e.g. Period 1" required />
                    <x-input name="start_time" label="Start time" type="time" required />
                    <x-input name="end_time" label="End time" type="time" required />
                    <x-select name="type" label="Period type" :options="$types" :selected="'class'" :placeholder="false" required />
                    <x-input name="sort_order" label="Display order" type="number" min="0" max="9999" :value="$nextOrder" help="Lower numbers appear first." />
                    <div class="field">
                        <span class="field-label">Status</span>
                        <input type="hidden" name="is_active" value="1">
                        <label class="checkbox"><input type="checkbox" checked disabled> <span>New periods are active</span></label>
                    </div>
                </div>
                <div style="margin-top:16px">
                    <x-button type="submit" variant="outline" icon="plus">Add time slot</x-button>
                </div>
            </form>
        </div>
    </div>
@endsection
