@extends('layouts.app')

@section('title', __('All Students'))

@section('content')
    <x-page-header eyebrow="{{ __('Students') }}" title="{{ __('All Students') }}" :subtitle="$session ? __('Enrolments for the :session session.', ['session' => $session->name]) : __('No academic session selected.')">
        <x-slot:actions>
            @can('students.create')
                <x-button :href="route('admissions.create')" variant="primary" icon="user-plus">{{ __('New admission') }}</x-button>
            @endcan
            @can('reports.export')
                <x-button :href="route('session-report.export')" variant="secondary" icon="download">{{ __('Export session') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-stat-card :label="__('Active students')" :value="number_format($summary['active'])" icon="users" tone="student" :meta="__('All sessions')" />
        <x-stat-card :label="__('New this session')" :value="number_format($summary['new'])" icon="user-plus" tone="collection" :meta="$session?->name ?? __('No session')" />
        <x-stat-card :label="__('Archive')" :value="number_format($summary['archived'])" icon="archive" tone="expense" :meta="__('Passed out, transferred, archived')" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ __('Student Directory') }}</h3><p class="card-sub">{{ $students->total() }} {{ __('record(s) found') }}</p></div>
            <form method="GET" class="filter-bar" style="margin:0">
                <div class="field grow" style="min-width:190px">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name, student ID, admission no, phone') }}">
                </div>
                <div class="field">
                    <select class="select" name="class_id">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (['active' => __('Active'), 'inactive' => __('Inactive')] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>{{ __('Student') }}</th><th>{{ __('Student ID') }}</th><th>{{ __('Class') }}</th><th>{{ __('Guardian') }}</th><th>{{ __('Status') }}</th><th>{{ __('Admitted') }}</th><th class="actions">{{ __('Actions') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        @php($enrollment = $student->enrollments->first())
                        <tr>
                            <td>
                                <div class="cell-main">
                                    @if ($student->photo_url)
                                        <img src="{{ $student->photo_url }}" alt="" class="table-avatar" style="object-fit:cover">
                                    @else
                                        <span class="table-avatar">{{ Str::substr($student->full_name, 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <div class="cell-title"><a href="{{ route('students.show', $student) }}">{{ $student->full_name }}</a></div>
                                        <div class="cell-sub">{{ ucfirst($student->gender) }} · {{ $student->date_of_birth?->age }} {{ __('yrs') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="mono" style="font-size:15px;font-weight:700">{{ $student->student_code ?? '—' }}</div>
                                <div class="cell-sub">{{ __('Student ID') }}</div>
                            </td>
                            <td>
                                @if ($enrollment)
                                    <div class="cell-title">{{ $enrollment->class?->name }}</div>
                                    <div class="cell-sub">{{ __('Section') }} {{ $enrollment->section?->name }} · {{ __('Roll') }} {{ $enrollment->roll_no }}</div>
                                @else
                                    <span class="muted">{{ __('Not enrolled') }}</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ $student->primaryGuardian()?->name ?? '—' }}</div>
                                <div class="cell-sub">{{ $student->primaryGuardian()?->phone }}</div>
                            </td>
                            <td><x-badge :color="$student->status_color">{{ $student->status_label }}</x-badge></td>
                            <td class="muted">{{ $student->admission_date?->format('d M Y') }}</td>
                            <td class="actions">
                                <a href="{{ route('students.show', $student) }}" class="btn-icon" title="{{ __('View') }}"><x-icon name="eye" :size="15" /></a>
                                @can('fees.collect')
                                    <a href="{{ route('fees.collection.show', $student) }}" class="btn-icon" title="{{ __('Collect fees') }}"><x-icon name="wallet" :size="15" /></a>
                                @endcan
                                @can('students.update')
                                    <a href="{{ route('students.edit', $student) }}" class="btn-icon" title="{{ __('Edit') }}"><x-icon name="edit" :size="15" /></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">{{ __('No students match your filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
@endsection
