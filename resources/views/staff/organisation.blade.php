@extends('layouts.app')

@section('title', 'Departments & Designations')

@section('content')
    <x-page-header eyebrow="Staff" title="Departments & Designations" subtitle="Organise the staff structure. Names can be renamed any time; entries in use cannot be deleted until staff are moved.">
        <x-slot:actions>
            <x-button :href="route('staff.index')" variant="secondary" icon="arrow-left">Staff directory</x-button>
            <x-button :href="route('staff.index')" variant="ghost" icon="user-plus">Add staff</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Departments</h3><p class="card-sub">{{ $departments->count() }} unit(s)</p></div>
                @can('staff.manage')
                    <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'new-department')">
                        <x-icon name="plus" :size="14" /> Add department
                    </button>
                @endcan
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Department</th><th class="num">Staff</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td>
                                    @can('staff.manage')
                                        <form method="POST" action="{{ route('staff.departments.update', $department) }}" style="display:flex;gap:6px;align-items:center">
                                            @csrf
                                            @method('PUT')
                                            <input class="input" type="text" name="name" value="{{ $department->name }}" maxlength="100" required aria-label="Department name">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Save name">{{ __('Save') }}</button>
                                        </form>
                                    @else
                                        <div class="cell-title">{{ $department->name }}</div>
                                    @endcan
                                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                                </td>
                                <td class="num">
                                    <span class="badge {{ $department->staff_count > 0 ? 'badge-info' : 'badge-muted' }}">{{ $department->staff_count }}</span>
                                </td>
                                <td class="actions">
                                    @can('staff.manage')
                                        <form method="POST" action="{{ route('staff.departments.destroy', $department) }}"
                                              onsubmit="return confirm(@js(__('Delete this department? This cannot be undone.')))" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Delete department" @disabled($department->staff_count > 0)>
                                                <x-icon name="trash" :size="15" />
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="table-empty">{{ __('No departments yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Designations</h3><p class="card-sub">{{ $designations->count() }} role(s)</p></div>
                @can('staff.manage')
                    <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'new-designation')">
                        <x-icon name="plus" :size="14" /> Add designation
                    </button>
                @endcan
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Designation</th><th class="num">Staff</th><th class="actions">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($designations as $designation)
                            <tr>
                                <td>
                                    @can('staff.manage')
                                        <form method="POST" action="{{ route('staff.designations.update', $designation) }}" style="display:flex;gap:6px;align-items:center">
                                            @csrf
                                            @method('PUT')
                                            <input class="input" type="text" name="name" value="{{ $designation->name }}" maxlength="100" required aria-label="Designation name">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Save name">{{ __('Save') }}</button>
                                        </form>
                                    @else
                                        <div class="cell-title">{{ $designation->name }}</div>
                                    @endcan
                                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                                </td>
                                <td class="num">
                                    <span class="badge {{ $designation->staff_count > 0 ? 'badge-info' : 'badge-muted' }}">{{ $designation->staff_count }}</span>
                                </td>
                                <td class="actions">
                                    @can('staff.manage')
                                        <form method="POST" action="{{ route('staff.designations.destroy', $designation) }}"
                                              onsubmit="return confirm(@js(__('Delete this designation? This cannot be undone.')))" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Delete designation" @disabled($designation->staff_count > 0)>
                                                <x-icon name="trash" :size="15" />
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="table-empty">{{ __('No designations yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('staff.manage')
        <x-modal name="new-department" :title="__('Add department')">
            <form method="POST" action="{{ route('staff.departments.store') }}">
                @csrf
                <div class="stack">
                    <x-input name="name" :label="__('Department name')" required placeholder="e.g. Science" />
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">{{ __('Add department') }}</x-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="new-designation" :title="__('Add designation')">
            <form method="POST" action="{{ route('staff.designations.store') }}">
                @csrf
                <div class="stack">
                    <x-input name="name" :label="__('Designation name')" required placeholder="e.g. Senior Teacher" />
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">{{ __('Add designation') }}</x-button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
