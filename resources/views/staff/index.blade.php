@extends('layouts.app')

@section('title', 'Staff Management')

@section('content')
    <x-page-header eyebrow="Staff" title="Staff Management" subtitle="Employee profiles, departments, designations and salary structures.">
        <x-slot:actions>
            <x-button :href="route('staff.organisation')" variant="secondary" icon="building">Departments & designations</x-button>
            <x-button :href="route('staff.attendance.index')" variant="secondary" icon="calendar-check">{{ __('Attendance') }}</x-button>
            <x-button :href="route('staff.leaves.index')" variant="secondary" icon="calendar">{{ __('Leave') }}</x-button>
            <x-button :href="route('staff.index', ['archived' => $showArchived ? 0 : 1])" variant="{{ $showArchived ? 'primary' : 'ghost' }}" icon="archive">
                {{ $showArchived ? 'Active staff' : 'Archived' }} ({{ $archivedCount }})
            </x-button>
            @can('staff.manage')
                <button type="button" class="btn btn-secondary" @click="$dispatch('open-modal', 'new-department')"><x-icon name="building" :size="15" /> Department</button>
                <button type="button" class="btn btn-secondary" @click="$dispatch('open-modal', 'new-designation')"><x-icon name="briefcase" :size="15" /> Designation</button>
                <x-button :href="route('staff.create')" variant="primary" icon="user-plus">Add staff</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-stat-card label="Active staff" :value="number_format($summary['active'])" icon="briefcase" tone="student" meta="Currently employed" />
        <x-stat-card label="Academic" :value="number_format($summary['academic'])" icon="graduation-cap" tone="collection" meta="Teaching staff" />
        <x-stat-card label="Non-academic" :value="number_format($summary['non_academic'])" icon="user-cog" tone="pending" meta="Support & administration" />
        <x-stat-card label="Payroll this month" :value="money($summary['payroll_month'])" icon="banknote" tone="expense" meta="Net salary rows" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">Staff Directory</h3><p class="card-sub">{{ $staff->total() }} profile(s)</p></div>
            <form method="GET" action="{{ route('staff.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field grow" style="min-width:190px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Name, employee no, phone"></div>
                <div class="field">
                    <select class="select" name="department_id">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="category">
                        <option value="">All categories</option>
                        <option value="academic" @selected(request('category') === 'academic')>Academic</option>
                        <option value="non_academic" @selected(request('category') === 'non_academic')>Non-academic</option>
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">Any status</option>
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'resigned' => 'Resigned'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Staff</th><th>Employee No</th><th>Department</th><th>Designation</th><th>Contact</th><th>Joined</th><th>Status</th><th class="num">Basic salary</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr>
                            <td>
                                <div class="cell-main">
                                    @if ($member->photo_path)
                                        <img src="{{ asset('storage/'.$member->photo_path) }}" alt="" class="table-avatar" style="object-fit:cover">
                                    @else
                                        <span class="table-avatar">{{ Str::substr($member->full_name, 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <div class="cell-title"><a href="{{ route('staff.show', $member) }}">{{ $member->full_name }}</a></div>
                                        <div class="cell-sub">
                                            {{ ucfirst($member->gender) }} · {{ ucfirst(str_replace('_', ' ', $member->employment_type)) }}
                                            · <span class="badge {{ $member->category === 'academic' ? 'badge-info' : 'badge-muted' }}">{{ $member->category_label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="mono" style="font-size:11px">{{ $member->employee_no }}</td>
                            <td>{{ $member->department?->name ?? '—' }}</td>
                            <td>{{ $member->designation?->name ?? '—' }}</td>
                            <td><div>{{ $member->phone }}</div><div class="cell-sub">{{ $member->email }}</div></td>
                            <td class="muted">{{ $member->joining_date?->format('d M Y') }}</td>
                            <td><x-badge :color="$member->status === 'active' ? 'success' : ($member->status === 'resigned' ? 'danger' : 'muted')">{{ ucfirst($member->status) }}</x-badge>
                                @if ($member->trashed())<span class="badge badge-muted" style="margin-left:4px">{{ __('Archived') }}</span>@endif
                            </td>
                            <td class="num">{{ money($member->basic_salary) }}</td>
                            <td class="actions">
                                <a href="{{ route('staff.show', $member) }}" class="btn-icon" title="View"><x-icon name="eye" :size="15" /></a>
                                @can('staff.manage')
                                    @if ($showArchived)
                                        <form method="POST" action="{{ route('staff.restore', $member) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn-icon" title="Restore staff"><x-icon name="refresh" :size="15" /></button>
                                        </form>
                                    @else
                                        <a href="{{ route('staff.edit', $member) }}" class="btn-icon" title="Edit"><x-icon name="edit" :size="15" /></a>
                                        <form method="POST" action="{{ route('staff.destroy', $member) }}" style="display:inline"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('Archive '.$member->full_name.'? Payroll history is preserved and the login is deactivated.') }})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Archive staff"><x-icon name="archive" :size="15" /></button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="table-empty">No staff profiles match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $staff->links() }}
    </div>

    @can('staff.manage')
        <x-modal name="new-department" title="Add department">
            <form method="POST" action="{{ route('staff.departments.store') }}">
                @csrf
                <div class="stack">
                    <x-input name="name" label="Department name" required placeholder="e.g. Science" />
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">Add department</x-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="new-designation" title="Add designation">
            <form method="POST" action="{{ route('staff.designations.store') }}">
                @csrf
                <div class="stack">
                    <x-input name="name" label="Designation name" required placeholder="e.g. Senior Teacher" />
                    <x-button type="submit" variant="primary" icon="plus" class="btn-block">Add designation</x-button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
