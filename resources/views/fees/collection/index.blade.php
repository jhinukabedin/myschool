@extends('layouts.app')

@section('title', __('Fee Collection'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Fee Collection') }}" :subtitle="$session ? __('Outstanding and collected fees for :session.', ['session' => $session->name]) : __('No academic session selected.')">
        <x-slot:actions>
            @can('fees.collect')
                <x-button :href="route('fees.quick.index')" variant="secondary" icon="zap">{{ __('Quick collect') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card :label="__('Collected')" :value="money($totals['collected'])" icon="circle-dollar" tone="collection" :meta="__('Session total')" />
        <x-stat-card :label="__('Outstanding (page)')" :value="money($totals['outstanding'])" icon="wallet" tone="pending" :meta="__('Visible students')" />
        <x-stat-card :label="__('Students')" :value="$enrollments->total()" icon="users" tone="student" :meta="__('Active enrolments')" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ __('Collection Ledger') }}</h3><p class="card-sub">{{ __('Select a student to collect against specific fee rows') }}</p></div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field grow" style="min-width:180px"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search student or guardian') }}"></div>
                <div class="field">
                    <select class="select" name="class_id">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Roll') }}</th><th>{{ __('Guardian') }}</th><th class="num">{{ __('Outstanding') }}</th><th class="actions">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($enrollments as $enrollment)
                        @php($due = $dues[$enrollment->student_id] ?? 0)
                        <tr>
                            <td>
                                <div class="cell-main">
                                    <span class="table-avatar">{{ Str::substr($enrollment->student?->full_name ?? '?', 0, 1) }}</span>
                                    <div><div class="cell-title">{{ $enrollment->student?->full_name }}</div><div class="cell-sub">Student ID: {{ $enrollment->student?->student_code }}</div></div>
                                </div>
                            </td>
                            <td>{{ $enrollment->class?->name }}</td>
                            <td>{{ $enrollment->roll_no }}</td>
                            <td><div>{{ $enrollment->student?->primaryGuardian()?->name ?? '—' }}</div><div class="cell-sub">{{ $enrollment->student?->primaryGuardian()?->phone }}</div></td>
                            <td class="num {{ $due > 0 ? 'text-danger' : 'text-success' }}" style="font-weight:600">{{ money($due) }}</td>
                            <td class="actions">
                                <a href="{{ route('fees.collection.show', $enrollment->student) }}" class="btn btn-secondary btn-sm">
                                    <x-icon name="wallet" :size="13" /> {{ __('Collect') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">{{ __('No students match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $enrollments->links() }}
    </div>
@endsection
