@extends('layouts.app')

@section('title', __('Scholarships'))

@section('content')
    <x-page-header eyebrow="{{ __('Fees') }}" title="{{ __('Scholarships') }}" :subtitle="__('Manage student scholarships and fee waivers per academic session.')">
        <x-slot:actions>
            @can('scholarships.create')
                <x-button :href="route('fees.scholarships.create')" variant="primary" icon="plus">{{ __('Add New Scholarship') }}</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="stat-grid" style="grid-template-columns:repeat(6,minmax(0,1fr))">
        <x-stat-card :label="__('Total Recipients')" :value="$cards['recipients']" icon="users" tone="student" :meta="__('All scholarships')" />
        <x-stat-card :label="__('Active')" :value="$cards['active_count']" icon="check-circle" tone="collection" :meta="__('Currently active')" />
        <x-stat-card :label="__('Expired')" :value="$cards['expired']" icon="clock" tone="pending" :meta="__('Past end date')" />
        <x-stat-card :label="__('Suspended')" :value="$cards['suspended']" icon="pause-circle" tone="pending" :meta="__('On hold')" />
        <x-stat-card :label="__('Monthly Waiver')" :value="money($cards['monthly_amount'])" icon="percent" tone="collection" :meta="__('Monthly value total')" />
        <x-stat-card :label="__('Year Waiver')" :value="money($cards['year_amount'])" icon="percent" tone="pending" :meta="__('Academic year total')" />
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3 class="card-title">{{ __('Scholarship List') }}</h3><p class="card-sub">{{ __('Filter by session, class, section, type or status') }}</p></div>
            <form method="GET" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field">
                    <select class="select" name="session_id">
                        <option value="">{{ __('All sessions') }}</option>
                        @foreach ($sessions as $s)
                            <option value="{{ $s->id }}" @selected($sessionId == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
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
                    <select class="select" name="section_id">
                        <option value="">{{ __('All sections') }}</option>
                        @foreach ($classes->flatMap->sections as $section)
                            <option value="{{ $section->id }}" @selected(request('section_id') == $section->id)>{{ $section->name }} ({{ $section->class?->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="scholarship_type">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}" @selected(request('scholarship_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="discount_method">
                        <option value="">{{ __('Discount') }}</option>
                        <option value="percent" @selected(request('discount_method') === 'percent')>{{ __('Percentage') }}</option>
                        <option value="fixed" @selected(request('discount_method') === 'fixed')>{{ __('Fixed') }}</option>
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="status">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Year') }}</th>
                    <th>{{ __('Scholarship') }}</th><th>{{ __('Discount') }}</th>
                    <th>{{ __('Categories') }}</th><th>{{ __('Period') }}</th>
                    <th>{{ __('Status') }}</th><th class="actions">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                    @forelse ($scholarships as $scholarship)
                        @php($enrollment = $scholarship->student->enrollments->firstWhere('academic_session_id', $scholarship->academic_session_id) ?? $scholarship->student->enrollments->first())
                        <tr>
                            <td>
                                <div class="cell-main">
                                    <span class="table-avatar">{{ Str::substr($scholarship->student?->full_name ?? '?', 0, 1) }}</span>
                                    <div>
                                        <div class="cell-title">{{ $scholarship->student?->full_name }}</div>
                                        <div class="cell-sub">Student ID: {{ $scholarship->student?->student_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $enrollment?->class?->name ?? '—' }}</td>
                            <td>{{ $scholarship->academicSession?->name ?? '—' }}</td>
                            <td>
                                <div class="cell-title">{{ $scholarship->scholarship_name }}</div>
                                <div class="cell-sub">{{ $scholarship->type_label }}</div>
                            </td>
                            <td class="num">
                                {{ $scholarship->discount_method === 'percent' ? rtrim(rtrim(number_format((float) $scholarship->discount_value, 2, '.', ''), '0'), '.').'%' : money($scholarship->discount_value) }}
                            </td>
                            <td class="muted" style="font-size:12px">
                                {{ $scholarship->applies_to === 'all' ? __('All fees') : $scholarship->items->pluck('category.name')->filter()->join(', ') }}
                            </td>
                            <td class="muted" style="font-size:12px">{{ $scholarship->start_date->format('d M Y') }} – {{ $scholarship->end_date->format('d M Y') }}</td>
                            <td><x-badge :color="$scholarship->status === 'active' ? 'success' : ($scholarship->status === 'suspended' ? 'danger' : ($scholarship->status === 'expired' ? 'muted' : 'warning'))">{{ $scholarship->status_label }}</x-badge></td>
                            <td class="actions" style="white-space:nowrap">
                                <a href="{{ route('fees.scholarships.show', $scholarship) }}" class="btn-icon"><x-icon name="eye" :size="15" /></a>
                                <a href="{{ route('fees.scholarships.history', $scholarship) }}" class="btn-icon"><x-icon name="clock" :size="15" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="table-empty">{{ __('No scholarships found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $scholarships->links() }}
    </div>
@endsection
