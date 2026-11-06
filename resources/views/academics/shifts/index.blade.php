@extends('layouts.app')

@section('title', __('Shifts'))

@section('content')
    <x-page-header eyebrow="{{ __('Academic management') }}" title="{{ __('Shifts') }}"
        subtitle="{{ __('Manage school shifts such as Morning and Afternoon. Shifts can be added, edited or removed.') }}">
        <x-slot:actions>
            <x-button :href="route('dashboard')" variant="secondary" icon="arrow-left">{{ __('Back') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('Add a shift') }}</h3>
                    <p class="card-sub">{{ __('Create a new shift for the school timetable.') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('shifts.store') }}" class="form-section">
                @csrf
                <div class="form-grid">
                    <x-input name="name" :label="__('Shift name')" :placeholder="__('e.g. Morning Shift')" required />
                    <x-input name="start_time" :label="__('Start time')" type="time" />
                    <x-input name="end_time" :label="__('End time')" type="time" />
                    <x-input name="sort_order" :label="__('Display order')" type="number" min="0" max="9999" :value="0" />
                    <div class="span-2">
                        <label class="field">
                            <span class="field-label">{{ __('Description') }}</span>
                            <textarea class="textarea" name="description" rows="2" maxlength="255" placeholder="{{ __('Optional note') }}"></textarea>
                        </label>
                    </div>
                    <div class="field">
                        <span class="field-label">{{ __('Status') }}</span>
                        <input type="hidden" name="is_active" value="0">
                        <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> <span>{{ __('Active shift') }}</span></label>
                    </div>
                </div>
                <div style="margin-top:16px">
                    <x-button type="submit" variant="primary" icon="plus">{{ __('Add shift') }}</x-button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('Existing shifts') }}</h3>
                    <p class="card-sub">{{ __(':count shift(s) configured', ['count' => $shifts->count()]) }}</p>
                </div>
            </div>

            @if ($shifts->isEmpty())
                <div class="form-section">
                    <div class="info-box">{{ __('No shifts have been created yet.') }}</div>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Time') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="actions">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shifts as $shift)
                                <tr>
                                    <td class="cell-title">{{ $shift->name }}</td>
                                    <td class="mono">{{ $shift->timeRange() ?: '-' }}</td>
                                    <td class="cell-sub">{{ $shift->description ?: '-' }}</td>
                                    <td><x-badge :color="$shift->is_active ? 'success' : 'muted'">{{ $shift->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                                    <td class="actions">
                                        <details>
                                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer;list-style:none">{{ __('Edit') }}</summary>
                                            <form method="POST" action="{{ route('shifts.update', $shift) }}" style="display:grid;gap:8px;margin-top:10px;min-width:260px">
                                                @csrf
                                                @method('PUT')
                                                <x-input name="name" :label="__('Shift name')" :value="$shift->name" required />
                                                <div style="display:flex;gap:6px">
                                                    <x-input name="start_time" :label="__('Start time')" type="time" :value="$shift->start_time ? substr($shift->start_time, 0, 5) : ''" style="width:120px" />
                                                    <x-input name="end_time" :label="__('End time')" type="time" :value="$shift->end_time ? substr($shift->end_time, 0, 5) : ''" style="width:120px" />
                                                </div>
                                                <x-input name="sort_order" :label="__('Display order')" type="number" min="0" max="9999" :value="$shift->sort_order" />
                                                <label class="field">
                                                    <span class="field-label">{{ __('Description') }}</span>
                                                    <textarea class="textarea" name="description" rows="2" maxlength="255">{{ $shift->description }}</textarea>
                                                </label>
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked($shift->is_active)> <span>{{ __('Active shift') }}</span></label>
                                                <x-button type="submit" variant="secondary" icon="save">{{ __('Save') }}</x-button>
                                            </form>
                                        </details>
                                        <form method="POST" action="{{ route('shifts.destroy', $shift) }}" onsubmit="return confirm(@js(__('Delete this shift?')))" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="{{ __('Delete shift') }}"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
